<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Enums\AdjustmentReasonEnum;
use App\Enums\OperationalActivityTypeEnum;
use App\Enums\StockMovementClassificationEnum;
use App\Enums\StockMovementOriginEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\StockMovementSequence;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Support\OperationalActivityService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class StockMovementService
{
    /**
     * @param StockMovementTypeResolver $typeResolver resolves movement type from source/destination
     */
    public function __construct(
        private readonly StockMovementTypeResolver $typeResolver,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createMovement(array $payload, User|null $user = null): StockMovement
    {
        if (!$user instanceof User) {
            $user = User::mustAuth();
        }

        $owner = $user->resolveScopeUser();
        $storeId = Typer::parseNullableInt($payload['store_id'] ?? null);
        $sourceStoreId = Typer::parseNullableInt($payload['source_store_id'] ?? null);
        $mode = Typer::parseNullableString($payload['mode'] ?? null) ?? 'transfer';

        $type = $this->typeResolver->resolve($mode, $sourceStoreId, $storeId);

        if (!$user->isAdmin()) {
            $isAllowedMode = ($mode === 'incoming' && $type === StockMovementTypeEnum::INCOMING) ||
                ($mode === 'consumption' && $type === StockMovementTypeEnum::CONSUMPTION);

            if (!$isAllowedMode ||
                $storeId !== $user->getAssignedStoreId()) {
                \abort(403);
            }
        }

        $note = Typer::parseNullableString($payload['note'] ?? null);
        $occurredAt = $user->isAdmin() && Typer::parseNullableString($payload['occurred_at'] ?? null) !== null
            ? Carbon::parse(Typer::assertString($payload['occurred_at']))
            : Carbon::now();
        /** @var array<int, array<string, mixed>> $rows */
        $rows = Typer::assertArray($payload['items'] ?? []);

        if ($type === StockMovementTypeEnum::INCOMING) {
            $this->resolveStore($owner, Typer::assertInt($storeId), 'store_id');
        }

        if ($type === StockMovementTypeEnum::TRANSFER) {
            $this->resolveStore($owner, Typer::assertInt($sourceStoreId), 'source_store_id');
            $this->resolveStore($owner, Typer::assertInt($storeId), 'store_id');
        }

        if ($type === StockMovementTypeEnum::ADJUSTMENT || $type === StockMovementTypeEnum::CONSUMPTION) {
            $this->resolveStore($owner, Typer::assertInt($storeId), 'store_id');
        }

        $persistedStoreId = $storeId;
        $persistedSourceStoreId = $type === StockMovementTypeEnum::TRANSFER ? $sourceStoreId : null;
        $affectedStoreIds = \array_values(\array_unique(\array_filter(
            [$persistedStoreId, $persistedSourceStoreId],
            static fn(int|null $id): bool => $id !== null,
        )));
        $itemIds = \array_values(\array_map(
            static fn(array $row): int => Typer::parseInt($row['item_id'] ?? null),
            $rows,
        ));
        $this->validateBackdating($owner, $affectedStoreIds, $itemIds, $occurredAt);

        return DB::transaction(function () use (
            $type,
            $persistedStoreId,
            $persistedSourceStoreId,
            $note,
            $rows,
            $user,
            $owner,
            $affectedStoreIds,
            $occurredAt,
        ): StockMovement {
            $lockedStores = $this->lockActiveStores($owner, $affectedStoreIds);
            $destinationStore = Typer::assertInstance(
                $lockedStores[Typer::assertInt($persistedStoreId)] ?? null,
                Store::class,
            );
            $sourceStore = $persistedSourceStoreId === null
                ? null
                : Typer::assertInstance($lockedStores[$persistedSourceStoreId] ?? null, Store::class);
            $year = (int) $occurredAt->format('Y');
            $number = StockMovementSequence::next($type, $year);

            $totals = [
                'quantity' => BigDecimal::zero(),
                'value' => 0.0,
                'items_count' => 0,
            ];

            $movement = StockMovement::query()->create([
                'user_id' => $owner->getKey(),
                'number' => $number,
                'type' => $type->value,
                'occurred_at' => $occurredAt,
                'origin' => StockMovementOriginEnum::MANUAL->value,
                'store_id' => $persistedStoreId,
                'source_store_id' => $persistedSourceStoreId,
                'note' => $note,
                'created_by' => $user->getKey(),
                'total_quantity' => 0,
                'total_value' => 0,
            ]);

            foreach ($rows as $row) {
                $rowPayload = $this->normaliseRow($type, Typer::assertArray($row));
                $itemQuery = Item::query();
                Item::scopeForUser($itemQuery, $owner);
                $item = $itemQuery
                    ->whereKey(Typer::parseInt($rowPayload['item_id']))
                    ->lockForUpdate()
                    ->first();

                if (!$item instanceof Item) {
                    $this->fail(['items' => \__('Item not found.')]);
                }

                $result = match ($type) {
                    StockMovementTypeEnum::INCOMING => $this->applyIncoming(
                        Typer::assertInstance($destinationStore, Store::class),
                        $item,
                        $rowPayload,
                    ),
                    StockMovementTypeEnum::TRANSFER => $this->applyTransfer(
                        Typer::assertInstance($sourceStore, Store::class),
                        Typer::assertInstance($destinationStore, Store::class),
                        $item,
                        $rowPayload,
                    ),
                    StockMovementTypeEnum::ADJUSTMENT => $this->applyAdjustment(
                        Typer::assertInstance($destinationStore, Store::class),
                        $item,
                        $rowPayload,
                    ),
                    StockMovementTypeEnum::CONSUMPTION => $this->applyConsumption(
                        Typer::assertInstance($destinationStore, Store::class),
                        $item,
                        $rowPayload,
                    ),
                    StockMovementTypeEnum::INVENTORY_RECONCILIATION => throw new RuntimeException('Inventory reconciliation must use createInventoryReconciliation().'),
                    StockMovementTypeEnum::REVERSAL => throw new RuntimeException('Reversals must use reverseMovement().'),
                };

                StockMovementItem::query()->create([
                    'stock_movement_id' => $movement->getKey(),
                    'item_id' => $item->getKey(),
                    'quantity' => $result['row_quantity'],
                    'unit_cost' => $item->getPurchasePrice(),
                    'unit_cost_estimated' => false,
                    'total' => $result['total'],
                    'quantity_before' => $result['quantity_before'],
                    'quantity_after' => $result['quantity_after'],
                    'quantity_difference' => $result['quantity_difference'],
                    'adjustment_reason' => $result['adjustment_reason'],
                    'classification' => $result['classification'],
                ]);

                $totals['quantity'] = $totals['quantity']->plus(
                    $this->decimal($result['quantity_difference'] ?? $result['row_quantity'] ?? 0)->abs(),
                );
                $totals['value'] += Typer::parseFloat($result['total']);
                ++$totals['items_count'];
            }

            $movement->update([
                'total_quantity' => $this->legacyQuantity($totals['quantity']),
                'items_count' => $totals['items_count'],
                'total_value' => \round($totals['value'], 2),
            ]);

            $this->notifyMovement($movement, $user, false, $type === StockMovementTypeEnum::TRANSFER);

            return $movement->fresh(['movementItems.item', 'store', 'sourceStore', 'creator']) ?? $movement;
        });
    }

    /**
     * Record an immutable reconciliation for an inventory session.
     *
     * This method records ledger rows only. The caller owns the physical
     * `store_items` update so snapshot and reconciliation remain atomic.
     *
     * @param array<int, array{session_item: InventorySessionItem, item: Item, expected: float|int|string, counted: float|int|string, difference: float|int|string, classification: StockMovementClassificationEnum, observation_started_at: Carbon|null}> $rows
     */
    public function createInventoryReconciliation(
        InventorySession $session,
        User $owner,
        array $rows,
        StockMovementOriginEnum $origin = StockMovementOriginEnum::INVENTORY,
    ): StockMovement|null {
        if ($rows === []) {
            return null;
        }

        $type = StockMovementTypeEnum::INVENTORY_RECONCILIATION;
        $number = StockMovementSequence::next($type, (int) $session->getCountedAt()->format('Y'));
        $movement = StockMovement::query()->create([
            'user_id' => $owner->getKey(),
            'number' => $number,
            'type' => $type->value,
            'occurred_at' => $session->getCountedAt(),
            'origin' => $origin->value,
            'inventory_session_id' => $session->getKey(),
            'store_id' => $session->getStore()->getKey(),
            'source_store_id' => null,
            'note' => $session->getNote(),
            'created_by' => $session->getCreatedBy(),
            'total_quantity' => 0,
            'items_count' => 0,
            'total_value' => 0,
        ]);

        $totalQuantity = BigDecimal::zero();
        $totalValue = 0.0;

        foreach ($rows as $row) {
            $difference = $this->decimal($row['difference']);
            $total = $this->money($difference->abs(), $row['item']->getPurchasePrice());

            StockMovementItem::query()->create([
                'stock_movement_id' => $movement->getKey(),
                'item_id' => $row['item']->getKey(),
                'quantity' => (string) $difference->abs(),
                'unit_cost' => $row['item']->getPurchasePrice(),
                'unit_cost_estimated' => false,
                'total' => $total,
                'quantity_before' => $row['expected'],
                'quantity_after' => $row['counted'],
                'quantity_difference' => (string) $difference,
                'adjustment_reason' => null,
                'classification' => $row['classification']->value,
                'observation_started_at' => $row['observation_started_at'],
                'inventory_session_item_id' => $row['session_item']->getKey(),
            ]);

            $totalQuantity = $totalQuantity->plus($difference->abs());
            $totalValue += Typer::parseFloat($total);
        }

        $movement->update([
            'total_quantity' => $this->legacyQuantity($totalQuantity),
            'items_count' => \count($rows),
            'total_value' => \round($totalValue, 2),
        ]);

        return $movement;
    }

    /**
     * Post an immutable compensating movement for one manual movement.
     */
    public function reverseMovement(StockMovement $movement, User $user, string $reason): StockMovement
    {
        if (!$user->isAdmin() || $movement->getUserId() !== $user->resolveScopeUser()->getKey()) {
            \abort(403);
        }

        if (\mb_trim($reason) === '') {
            $this->fail(['reversal_reason' => \__('A reversal reason is required.')]);
        }

        return DB::transaction(function () use ($movement, $user, $reason): StockMovement {
            $storeIds = \array_values(\array_filter(
                [$movement->getStoreId(), $movement->getSourceStoreId()],
                static fn(int|null $id): bool => $id !== null,
            ));
            $lockedStores = $this->lockActiveStores($user->resolveScopeUser(), $storeIds);
            $movement = StockMovement::query()->whereKey($movement->getKey())->lockForUpdate()->firstOrFail();
            $movement->loadMissing(['movementItems.item', 'store', 'sourceStore']);

            $type = $movement->getType();

            if (
                $type === StockMovementTypeEnum::INVENTORY_RECONCILIATION ||
                $type === StockMovementTypeEnum::REVERSAL ||
                $movement->getOrigin() !== StockMovementOriginEnum::MANUAL
            ) {
                $this->fail(['stock_movement' => \__('This stock movement cannot be reversed.')]);
            }

            if ($movement->getReversedAt() !== null || StockMovement::query()->where('reversal_of_id', $movement->getKey())->exists()) {
                $this->fail(['stock_movement' => \__('This stock movement has already been reversed.')]);
            }
            $destinationStoreId = Typer::assertInt($movement->getStoreId());
            $destinationStore = Typer::assertInstance($lockedStores[$destinationStoreId] ?? null, Store::class);
            $sourceStoreId = $movement->getSourceStoreId();
            $sourceStore = $sourceStoreId === null
                ? null
                : Typer::assertInstance($lockedStores[$sourceStoreId] ?? null, Store::class);

            $reversal = StockMovement::query()->create([
                'user_id' => $movement->getUserId(),
                'number' => StockMovementSequence::next(StockMovementTypeEnum::REVERSAL, (int) Carbon::now()->format('Y')),
                'type' => StockMovementTypeEnum::REVERSAL->value,
                'occurred_at' => Carbon::now(),
                'origin' => StockMovementOriginEnum::REVERSAL->value,
                'reversal_of_id' => $movement->getKey(),
                'store_id' => $movement->getStoreId(),
                'source_store_id' => $movement->getSourceStoreId(),
                'note' => $movement->getNote(),
                'reversal_reason' => \mb_trim($reason),
                'created_by' => $user->getKey(),
                'total_quantity' => $movement->getTotalQuantity(),
                'items_count' => $movement->getItemsCount(),
                'total_value' => -$movement->getTotalValue(),
            ]);

            $movementItems = $movement->getMovementItems()->sortBy(
                static fn(StockMovementItem $row): int => $row->getItemId(),
            );

            foreach ($movementItems as $movementItem) {
                $item = $movementItem->getItem();

                $before = $this->currentQuantity($destinationStore, $item);

                if ($type === StockMovementTypeEnum::INCOMING) {
                    $this->reverseIncoming($destinationStore, $item, $movementItem);
                } elseif ($type === StockMovementTypeEnum::TRANSFER) {
                    $this->reverseTransfer(
                        Typer::assertInstance($sourceStore, Store::class),
                        $destinationStore,
                        $item,
                        $movementItem,
                    );
                } elseif ($type === StockMovementTypeEnum::ADJUSTMENT) {
                    $this->reverseAdjustment($destinationStore, $item, $movementItem);
                } else {
                    $this->reverseConsumption($destinationStore, $item, $movementItem);
                }

                $after = $this->currentQuantity($destinationStore, $item);

                StockMovementItem::query()->create([
                    'stock_movement_id' => $reversal->getKey(),
                    'item_id' => $item->getKey(),
                    'quantity' => $movementItem->getQuantity(),
                    'unit_cost' => $movementItem->getUnitCost() ?? $item->getPurchasePrice(),
                    'unit_cost_estimated' => $movementItem->getUnitCost() === null || $movementItem->isUnitCostEstimated(),
                    'total' => -$movementItem->getTotal(),
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'quantity_difference' => (string) $this->decimal(Typer::assertScalar($movementItem->getQuantityDifference()))->negated(),
                    'adjustment_reason' => $movementItem->getAdjustmentReason()?->value,
                    'classification' => $movementItem->getClassification()?->value,
                ]);
            }

            $movement->update(['reversed_at' => Carbon::now()]);

            $this->notifyMovement($reversal, $user, true, $type === StockMovementTypeEnum::TRANSFER);

            return $reversal->fresh(['movementItems.item', 'store', 'sourceStore', 'creator']) ?? $reversal;
        }, 3);
    }

    /**
     * Dispatch one manual movement activity to every affected store channel.
     */
    private function notifyMovement(StockMovement $movement, User $user, bool $reversed, bool $isTransfer): void
    {
        $destinations = [];
        $sourceStore = $movement->getSourceStore();
        $store = $movement->getStore();

        if ($isTransfer) {
            if ($sourceStore instanceof Store) {
                $destinations[] = ['store' => $sourceStore, 'perspective' => $reversed ? 'incoming' : 'outgoing'];
            }

            if ($store instanceof Store) {
                $destinations[] = ['store' => $store, 'perspective' => $reversed ? 'outgoing' : 'incoming'];
            }
        } elseif ($store instanceof Store) {
            $destinations[] = ['store' => $store, 'perspective' => null];
        }

        OperationalActivityService::dispatch(
            match (true) {
                $isTransfer && $reversed => OperationalActivityTypeEnum::STOCK_TRANSFER_REVERSED,
                $isTransfer => OperationalActivityTypeEnum::STOCK_TRANSFER_CREATED,
                $reversed => OperationalActivityTypeEnum::STOCK_MOVEMENT_REVERSED,
                default => OperationalActivityTypeEnum::STOCK_MOVEMENT_CREATED,
            },
            $user,
            Carbon::now('UTC')->toIso8601String(),
            Resolver::resolveUrlGenerator()->route('stock-movements.show', ['stockMovement' => $movement->getKey()]),
            $destinations,
            [
                'Slack movement number' => $movement->getNumber(),
                'Slack item count' => (string) $movement->getItemsCount(),
                'Slack total quantity' => (string) $movement->getTotalQuantity(),
                'Slack total value' => \number_format($movement->getTotalValue(), 2, ',', ' ') . ' Kč',
            ],
        );
    }

    /**
     * Resolve an owned store by id.
     */
    private function resolveStore(User $user, int $storeId, string $field): Store
    {
        $storeQuery = Store::query();
        Store::scopeForUser($storeQuery, $user);
        $store = $storeQuery
            ->whereKey($storeId)
            ->first();

        if (!$store instanceof Store) {
            $this->fail([$field => \__('Store not found.')]);
        }

        return $store;
    }

    /**
     * Lock every affected store in stable id order and reject inactive targets.
     *
     * @param list<int> $storeIds
     *
     * @return array<int, Store>
     */
    private function lockActiveStores(User $owner, array $storeIds): array
    {
        \sort($storeIds);
        $query = Store::query();
        Store::scopeForUser($query, $owner);
        $stores = $query->whereIn('id', $storeIds)->orderBy('id')->lockForUpdate()->get();
        $locked = [];

        foreach ($stores as $value) {
            $store = Typer::assertInstance($value, Store::class);
            if (!$store->isActive()) {
                $this->fail(['store_id' => \__('Store not found.')]);
            }
            $locked[$store->getKey()] = $store;
        }

        if (\count($locked) !== \count($storeIds)) {
            $this->fail(['store_id' => \__('Store not found.')]);
        }

        return $locked;
    }

    /**
     * @param array<string, array<array-key, mixed>|string> $messages
     */
    private function fail(array $messages): never
    {
        $validator = Resolver::resolveValidatorFactory()->make([], []);
        $thrower = new Thrower($validator);

        foreach ($messages as $key => $message) {
            $thrower->message($key, $message);
        }

        $thrower->throw();
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normaliseRow(StockMovementTypeEnum $type, array $row): array
    {
        $itemId = Typer::parseInt($row['item_id'] ?? 0);

        if ($itemId <= 0) {
            $this->fail(['items' => \__('Item is required for every row.')]);
        }

        return match ($type) {
            StockMovementTypeEnum::INCOMING, StockMovementTypeEnum::TRANSFER, StockMovementTypeEnum::CONSUMPTION => [
                'item_id' => $itemId,
                'quantity' => (string) $this->decimal($row['quantity'] ?? 0),
            ],
            StockMovementTypeEnum::ADJUSTMENT => [
                'item_id' => $itemId,
                'quantity_after' => (string) $this->decimal($row['quantity_after'] ?? 0),
                'adjustment_reason' => Typer::assertString($row['adjustment_reason'] ?? AdjustmentReasonEnum::OTHER->value),
            ],
            StockMovementTypeEnum::INVENTORY_RECONCILIATION => throw new RuntimeException('Inventory reconciliation rows use a dedicated flow.'),
            StockMovementTypeEnum::REVERSAL => throw new RuntimeException('Reversal rows use a dedicated flow.'),
        };
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function applyIncoming(Store $destination, Item $item, array $row): array
    {
        $quantity = $this->decimal($row['quantity']);
        $unitPrice = $item->getPurchasePrice();
        $storeItem = $this->lockStoreItem($destination, $item);
        $before = $this->decimal($storeItem->getAttribute('quantity'));
        $after = $before->plus($quantity);

        $storeItem->update(['quantity' => (string) $after]);

        return [
            'row_quantity' => (string) $quantity,
            'total' => $this->money($quantity, $unitPrice),
            'quantity_before' => (string) $before,
            'quantity_after' => (string) $after,
            'quantity_difference' => (string) $quantity,
            'adjustment_reason' => null,
            'classification' => null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function applyTransfer(Store $source, Store $destination, Item $item, array $row): array
    {
        $quantity = $this->decimal($row['quantity']);
        $unitPrice = $item->getPurchasePrice();
        $sourceItem = $this->lockStoreItem($source, $item);
        $current = $this->decimal($sourceItem->getAttribute('quantity'));

        if ($quantity->isGreaterThan($current)) {
            $this->fail([
                'items' => \__('You cannot remove :qty from ":title" (only :current available).', [
                    'qty' => (string) $quantity,
                    'title' => $item->getTitle(),
                    'current' => (string) $current,
                ]),
            ]);
        }

        $sourceItem->update(['quantity' => (string) $current->minus($quantity)]);

        $destinationItem = $this->lockStoreItem($destination, $item);
        $destinationBefore = $this->decimal($destinationItem->getAttribute('quantity'));
        $destinationAfter = $destinationBefore->plus($quantity);
        $destinationItem->update(['quantity' => (string) $destinationAfter]);

        return [
            'row_quantity' => (string) $quantity,
            'total' => $this->money($quantity, $unitPrice),
            'quantity_before' => (string) $current,
            'quantity_after' => (string) $destinationAfter,
            'quantity_difference' => (string) $quantity->negated(),
            'adjustment_reason' => null,
            'classification' => null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function applyConsumption(Store $store, Item $item, array $row): array
    {
        $quantity = $this->decimal($row['quantity']);
        $storeItem = $this->lockStoreItem($store, $item);
        $before = $this->decimal($storeItem->getAttribute('quantity'));

        if ($quantity->isGreaterThan($before)) {
            $this->fail([
                'items' => \__('You cannot consume :qty from ":title" (only :current available).', [
                    'qty' => (string) $quantity,
                    'title' => $item->getTitle(),
                    'current' => (string) $before,
                ]),
            ]);
        }

        $after = $before->minus($quantity);
        $storeItem->update(['quantity' => (string) $after]);

        return [
            'row_quantity' => (string) $quantity,
            'total' => $this->money($quantity, $item->getPurchasePrice()),
            'quantity_before' => (string) $before,
            'quantity_after' => (string) $after,
            'quantity_difference' => (string) $quantity->negated(),
            'adjustment_reason' => null,
            'classification' => StockMovementClassificationEnum::CONSUMPTION->value,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function applyAdjustment(Store $store, Item $item, array $row): array
    {
        $after = $this->decimal($row['quantity_after']);
        $storeItem = $this->lockStoreItem($store, $item);
        $before = $this->decimal($storeItem->getAttribute('quantity'));
        $difference = $after->minus($before);
        $classification = StockMovementClassificationEnum::from(Typer::assertString($row['adjustment_reason']));

        if (
            ($difference->isNegative() && !$classification->supportsNegativeDifference()) ||
            ($difference->isPositive() && !$classification->supportsPositiveDifference())
        ) {
            $this->fail(['items' => \__('The selected adjustment reason does not match the quantity difference.')]);
        }

        $storeItem->update(['quantity' => (string) $after]);

        return [
            'row_quantity' => null,
            'total' => $this->money($difference->abs(), $item->getPurchasePrice()),
            'quantity_before' => (string) $before,
            'quantity_after' => (string) $after,
            'quantity_difference' => (string) $difference,
            'adjustment_reason' => $classification->value,
            'classification' => $classification->value,
        ];
    }

    /**
     * Lock or create a store_items row for the given store and item.
     *
     * Two concurrent first-time callers could both see "no row" and
     * both `create()`, with the second hitting the unique-key
     * constraint. We retry the lookup once after a duplicate-key
     * failure; the first caller's `create()` is now visible inside
     * the same transaction.
     */
    private function lockStoreItem(Store $store, Item $item): StoreItem
    {
        $existing = StoreItem::query()
            ->where('store_id', $store->getKey())
            ->where('item_id', $item->getKey())
            ->lockForUpdate()
            ->first();

        if ($existing instanceof StoreItem) {
            return $existing;
        }

        try {
            return StoreItem::query()->create([
                'store_id' => $store->getKey(),
                'item_id' => $item->getKey(),
                'quantity' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = StoreItem::query()
                ->where('store_id', $store->getKey())
                ->where('item_id', $item->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing instanceof StoreItem) {
                return $existing;
            }

            throw new RuntimeException('Store item race could not be resolved.');
        }
    }

    /**
     * Lock an existing store_items row, failing if it does not exist.
     */
    private function findStoreItem(Store $store, Item $item): StoreItem
    {
        $existing = StoreItem::query()
            ->where('store_id', $store->getKey())
            ->where('item_id', $item->getKey())
            ->lockForUpdate()
            ->first();

        if (!$existing instanceof StoreItem) {
            $this->fail([
                'stock_movement' => \__(
                    'Cannot reverse this movement because the inventory row for ":title" at ":store" is missing.',
                    ['title' => $item->getTitle(), 'store' => $store->getName()],
                ),
            ]);
        }

        return $existing;
    }

    /**
     * Read current stock without creating a missing row.
     */
    private function currentQuantity(Store $store, Item $item): float|int
    {
        $storeItem = StoreItem::query()
            ->where('store_id', $store->getKey())
            ->where('item_id', $item->getKey())
            ->first();

        return $storeItem instanceof StoreItem ? $storeItem->getQuantity() : 0;
    }

    /**
     * Reject a manual event that predates a closed physical count it would invalidate.
     *
     * @param list<int> $storeIds
     * @param list<int> $itemIds
     */
    private function validateBackdating(User $owner, array $storeIds, array $itemIds, Carbon $occurredAt): void
    {
        if ($storeIds === [] || $itemIds === []) {
            return;
        }

        $hasNewerCount = DB::table('inventory_session_items')
            ->join('inventory_sessions', 'inventory_sessions.id', '=', 'inventory_session_items.session_id')
            ->where('inventory_sessions.user_id', $owner->getKey())
            ->where('inventory_sessions.status', 'closed')
            ->whereIn('inventory_sessions.store_id', $storeIds)
            ->whereIn('inventory_session_items.item_id', $itemIds)
            ->whereRaw(
                'COALESCE(inventory_session_items.counted_at, inventory_sessions.counted_at) > ?',
                [$occurredAt->toDateTimeString()],
            )
            ->exists();

        if ($hasNewerCount) {
            $this->fail([
                'occurred_at' => \__('A stock movement cannot be posted before the latest closed inventory for an affected item.'),
            ]);
        }
    }

    /**
     * Reverse an incoming movement row.
     */
    private function reverseIncoming(Store $destination, Item $item, StockMovementItem $movementItem): void
    {
        $quantity = $this->decimal(Typer::assertScalar($movementItem->getQuantity()));
        $storeItem = $this->findStoreItem($destination, $item);
        $current = $this->decimal($storeItem->getAttribute('quantity'));

        if ($quantity->isGreaterThan($current)) {
            $this->fail([
                'stock_movement' => \__(
                    'Cannot reverse this movement because it would make ":title" negative at ":store".',
                    ['title' => $item->getTitle(), 'store' => $destination->getName()],
                ),
            ]);
        }

        $storeItem->update(['quantity' => (string) $current->minus($quantity)]);
    }

    /**
     * Reverse a transfer movement row.
     */
    private function reverseTransfer(Store $source, Store $destination, Item $item, StockMovementItem $movementItem): void
    {
        $quantity = $this->decimal(Typer::assertScalar($movementItem->getQuantity()));

        $sourceItem = $this->findStoreItem($source, $item);
        $destinationItem = $this->findStoreItem($destination, $item);

        $destinationCurrent = $this->decimal($destinationItem->getAttribute('quantity'));

        if ($quantity->isGreaterThan($destinationCurrent)) {
            $this->fail([
                'stock_movement' => \__(
                    'Cannot reverse this movement because it would make ":title" negative at ":store".',
                    ['title' => $item->getTitle(), 'store' => $destination->getName()],
                ),
            ]);
        }

        $sourceItem->update(['quantity' => (string) $this->decimal($sourceItem->getAttribute('quantity'))->plus($quantity)]);
        $destinationItem->update(['quantity' => (string) $destinationCurrent->minus($quantity)]);
    }

    /**
     * Reverse a manual consumption row.
     */
    private function reverseConsumption(Store $store, Item $item, StockMovementItem $movementItem): void
    {
        $quantity = $this->decimal(Typer::assertScalar($movementItem->getQuantity()));
        $storeItem = $this->findStoreItem($store, $item);

        $storeItem->update(['quantity' => (string) $this->decimal($storeItem->getAttribute('quantity'))->plus($quantity)]);
    }

    /**
     * Reverse an adjustment movement row.
     */
    private function reverseAdjustment(Store $destination, Item $item, StockMovementItem $movementItem): void
    {
        $storeItem = $this->findStoreItem($destination, $item);
        $difference = $this->decimal(Typer::assertScalar($movementItem->getQuantityDifference()));
        $after = $this->decimal($storeItem->getAttribute('quantity'))->minus($difference);

        if ($after->isNegative()) {
            $this->fail([
                'stock_movement' => \__(
                    'Cannot reverse this movement because it would make ":title" negative at ":store".',
                    ['title' => $item->getTitle(), 'store' => $destination->getName()],
                ),
            ]);
        }

        $storeItem->update(['quantity' => (string) $after]);
    }

    /**
     * Parse an exact stock quantity at the canonical scale.
     */
    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) Typer::assertScalar($value))->toScale(3, RoundingMode::Unnecessary);
    }

    /**
     * Calculate a line value rounded to currency precision.
     */
    private function money(BigDecimal $quantity, float $unitPrice): string
    {
        return (string) $quantity
            ->multipliedBy(BigDecimal::of((string) $unitPrice))
            ->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * Maintain the deprecated integer aggregate during the compatibility window.
     */
    private function legacyQuantity(BigDecimal $quantity): int
    {
        $value = (float) (string) $quantity;

        return $value === \floor($value) ? (int) $value : 0;
    }
}
