<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdjustmentReasonEnum;
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
            if ($type !== StockMovementTypeEnum::CONSUMPTION || $storeId !== $user->getAssignedStoreId()) {
                \abort(403);
            }
        }

        $note = Typer::parseNullableString($payload['note'] ?? null);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = Typer::assertArray($payload['items'] ?? []);

        $sourceStore = null;
        $destinationStore = null;

        if ($type === StockMovementTypeEnum::INCOMING) {
            $destinationStore = $this->resolveStore($owner, Typer::assertInt($storeId), 'store_id');
        }

        if ($type === StockMovementTypeEnum::TRANSFER) {
            $sourceStore = $this->resolveStore($owner, Typer::assertInt($sourceStoreId), 'source_store_id');
            $destinationStore = $this->resolveStore($owner, Typer::assertInt($storeId), 'store_id');
        }

        if ($type === StockMovementTypeEnum::ADJUSTMENT || $type === StockMovementTypeEnum::CONSUMPTION) {
            $destinationStore = $this->resolveStore($owner, Typer::assertInt($storeId), 'store_id');
        }

        $persistedStoreId = $storeId;
        $persistedSourceStoreId = $type === StockMovementTypeEnum::TRANSFER ? $sourceStoreId : null;

        return DB::transaction(function () use (
            $type,
            $persistedStoreId,
            $persistedSourceStoreId,
            $note,
            $rows,
            $user,
            $owner,
            $sourceStore,
            $destinationStore,
        ): StockMovement {
            $year = (int) Carbon::now()->format('Y');
            $number = StockMovementSequence::next($type, $year, $owner->getKey());

            $totals = [
                'quantity' => 0,
                'value' => 0.0,
            ];

            $movement = StockMovement::query()->create([
                'user_id' => $owner->getKey(),
                'number' => $number,
                'type' => $type->value,
                'occurred_at' => Carbon::now(),
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
                };

                StockMovementItem::query()->create([
                    'stock_movement_id' => $movement->getKey(),
                    'item_id' => $item->getKey(),
                    'quantity' => $result['row_quantity'],
                    'total' => $result['total'],
                    'quantity_before' => $result['quantity_before'],
                    'quantity_after' => $result['quantity_after'],
                    'quantity_difference' => $result['quantity_difference'],
                    'adjustment_reason' => $result['adjustment_reason'],
                    'classification' => $result['classification'],
                ]);

                $totals['quantity'] += \abs(Typer::parseInt($result['quantity_difference'] ?? $result['row_quantity'] ?? 0));
                $totals['value'] += Typer::parseFloat($result['total']);
            }

            $movement->update([
                'total_quantity' => $totals['quantity'],
                'total_value' => \round($totals['value'], 2),
            ]);

            return $movement->fresh(['movementItems.item', 'store', 'sourceStore', 'creator']) ?? $movement;
        });
    }

    /**
     * Record an immutable reconciliation for an inventory session.
     *
     * This method records ledger rows only. The caller owns the physical
     * `store_items` update so snapshot and reconciliation remain atomic.
     *
     * @param array<int, array{session_item: InventorySessionItem, item: Item, expected: int, counted: int, difference: int, classification: StockMovementClassificationEnum, observation_started_at: Carbon|null}> $rows
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
        $number = StockMovementSequence::next($type, (int) $session->getCountedAt()->format('Y'), $owner->getKey());
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
            'total_value' => 0,
        ]);

        $totalQuantity = 0;
        $totalValue = 0.0;

        foreach ($rows as $row) {
            $difference = $row['difference'];
            $total = \round(\abs($difference) * $row['item']->getPurchasePrice(), 2);

            StockMovementItem::query()->create([
                'stock_movement_id' => $movement->getKey(),
                'item_id' => $row['item']->getKey(),
                'quantity' => \abs($difference),
                'total' => $total,
                'quantity_before' => $row['expected'],
                'quantity_after' => $row['counted'],
                'quantity_difference' => $difference,
                'adjustment_reason' => null,
                'classification' => $row['classification']->value,
                'observation_started_at' => $row['observation_started_at'],
                'inventory_session_item_id' => $row['session_item']->getKey(),
            ]);

            $totalQuantity += \abs($difference);
            $totalValue += $total;
        }

        $movement->update([
            'total_quantity' => $totalQuantity,
            'total_value' => \round($totalValue, 2),
        ]);

        return $movement;
    }

    /**
     * Delete a movement and reverse its effect on store inventory.
     */
    public function deleteMovement(StockMovement $movement): void
    {
        DB::transaction(function () use ($movement): void {
            $movement->loadMissing(['movementItems.item', 'store', 'sourceStore']);

            $type = $movement->getType();

            if ($type === StockMovementTypeEnum::INVENTORY_RECONCILIATION) {
                $this->fail(['stock_movement' => \__('Inventory reconciliations are immutable.')]);
            }
            $destinationStore = $movement->getStore();
            $sourceStore = $movement->getSourceStore();

            if ($destinationStore === null) {
                $this->fail([
                    'stock_movement' => \__('Cannot delete this movement because the destination store no longer exists.'),
                ]);
            }

            if ($type === StockMovementTypeEnum::TRANSFER && $sourceStore === null) {
                $this->fail([
                    'stock_movement' => \__('Cannot delete this movement because the source store no longer exists.'),
                ]);
            }

            foreach ($movement->getMovementItems() as $movementItem) {
                $item = $movementItem->getItem();

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
            }

            $movement->delete();
        });
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
                'quantity' => (int) Typer::assertScalar($row['quantity'] ?? 0),
            ],
            StockMovementTypeEnum::ADJUSTMENT => [
                'item_id' => $itemId,
                'quantity_after' => Typer::parseInt($row['quantity_after'] ?? 0),
                'adjustment_reason' => Typer::assertString($row['adjustment_reason'] ?? AdjustmentReasonEnum::OTHER->value),
            ],
            StockMovementTypeEnum::INVENTORY_RECONCILIATION => throw new RuntimeException('Inventory reconciliation rows use a dedicated flow.'),
        };
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function applyIncoming(Store $destination, Item $item, array $row): array
    {
        $quantity = Typer::parseInt($row['quantity']);
        $unitPrice = $item->getPurchasePrice();
        $storeItem = $this->lockStoreItem($destination, $item);
        $before = $storeItem->getQuantity();
        $after = $before + $quantity;

        $storeItem->update(['quantity' => $after]);

        return [
            'row_quantity' => $quantity,
            'total' => \round($quantity * $unitPrice, 2),
            'quantity_before' => $before,
            'quantity_after' => $after,
            'quantity_difference' => $quantity,
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
        $quantity = Typer::parseInt($row['quantity']);
        $unitPrice = $item->getPurchasePrice();
        $sourceItem = $this->lockStoreItem($source, $item);
        $current = $sourceItem->getQuantity();

        if ($quantity > $current) {
            $this->fail([
                'items' => \__('You cannot remove :qty from ":title" (only :current available).', [
                    'qty' => $quantity,
                    'title' => $item->getTitle(),
                    'current' => $current,
                ]),
            ]);
        }

        $sourceItem->update(['quantity' => $current - $quantity]);

        $destinationItem = $this->lockStoreItem($destination, $item);
        $destinationBefore = $destinationItem->getQuantity();
        $destinationAfter = $destinationBefore + $quantity;
        $destinationItem->update(['quantity' => $destinationAfter]);

        return [
            'row_quantity' => $quantity,
            'total' => \round($quantity * $unitPrice, 2),
            'quantity_before' => $current,
            'quantity_after' => $destinationAfter,
            'quantity_difference' => -$quantity,
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
        $quantity = Typer::parseInt($row['quantity']);
        $storeItem = $this->lockStoreItem($store, $item);
        $before = $storeItem->getQuantity();

        if ($quantity > $before) {
            $this->fail([
                'items' => \__('You cannot consume :qty from ":title" (only :current available).', [
                    'qty' => $quantity,
                    'title' => $item->getTitle(),
                    'current' => $before,
                ]),
            ]);
        }

        $after = $before - $quantity;
        $storeItem->update(['quantity' => $after]);

        return [
            'row_quantity' => $quantity,
            'total' => \round($quantity * $item->getPurchasePrice(), 2),
            'quantity_before' => $before,
            'quantity_after' => $after,
            'quantity_difference' => -$quantity,
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
        $after = Typer::parseInt($row['quantity_after']);
        $storeItem = $this->lockStoreItem($store, $item);
        $before = $storeItem->getQuantity();
        $difference = $after - $before;
        $classification = StockMovementClassificationEnum::from(Typer::assertString($row['adjustment_reason']));

        if (
            ($difference < 0 && !$classification->supportsNegativeDifference()) ||
            ($difference > 0 && !$classification->supportsPositiveDifference())
        ) {
            $this->fail(['items' => \__('The selected adjustment reason does not match the quantity difference.')]);
        }

        $storeItem->update(['quantity' => $after]);

        return [
            'row_quantity' => null,
            'total' => \round(\abs($difference) * $item->getPurchasePrice(), 2),
            'quantity_before' => $before,
            'quantity_after' => $after,
            'quantity_difference' => $difference,
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
                    'Cannot delete this movement because the inventory row for ":title" at ":store" is missing.',
                    ['title' => $item->getTitle(), 'store' => $store->getName()],
                ),
            ]);
        }

        return $existing;
    }

    /**
     * Reverse an incoming movement row.
     */
    private function reverseIncoming(Store $destination, Item $item, StockMovementItem $movementItem): void
    {
        $quantity = Typer::assertInt($movementItem->getQuantity());
        $storeItem = $this->findStoreItem($destination, $item);
        $current = $storeItem->getQuantity();

        if ($quantity > $current) {
            $this->fail([
                'stock_movement' => \__(
                    'Cannot delete this movement because it would make ":title" negative at ":store".',
                    ['title' => $item->getTitle(), 'store' => $destination->getName()],
                ),
            ]);
        }

        $storeItem->update(['quantity' => $current - $quantity]);
    }

    /**
     * Reverse a transfer movement row.
     */
    private function reverseTransfer(Store $source, Store $destination, Item $item, StockMovementItem $movementItem): void
    {
        $quantity = Typer::assertInt($movementItem->getQuantity());

        $sourceItem = $this->findStoreItem($source, $item);
        $destinationItem = $this->findStoreItem($destination, $item);

        $destinationCurrent = $destinationItem->getQuantity();

        if ($quantity > $destinationCurrent) {
            $this->fail([
                'stock_movement' => \__(
                    'Cannot delete this movement because it would make ":title" negative at ":store".',
                    ['title' => $item->getTitle(), 'store' => $destination->getName()],
                ),
            ]);
        }

        $sourceItem->update(['quantity' => $sourceItem->getQuantity() + $quantity]);
        $destinationItem->update(['quantity' => $destinationCurrent - $quantity]);
    }

    /**
     * Reverse a manual consumption row.
     */
    private function reverseConsumption(Store $store, Item $item, StockMovementItem $movementItem): void
    {
        $quantity = Typer::assertInt($movementItem->getQuantity());
        $storeItem = $this->findStoreItem($store, $item);

        $storeItem->update(['quantity' => $storeItem->getQuantity() + $quantity]);
    }

    /**
     * Reverse an adjustment movement row.
     */
    private function reverseAdjustment(Store $destination, Item $item, StockMovementItem $movementItem): void
    {
        $before = Typer::assertInt($movementItem->getQuantityBefore());
        $storeItem = $this->findStoreItem($destination, $item);

        $storeItem->update(['quantity' => $before]);
    }
}
