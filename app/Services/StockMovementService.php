<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdjustmentReasonEnum;
use App\Enums\StockMovementTypeEnum;
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

        $storeId = Typer::parseNullableInt($payload['store_id'] ?? null);
        $sourceStoreId = Typer::parseNullableInt($payload['source_store_id'] ?? null);
        $isAdjustment = ($payload['mode'] ?? null) === 'adjustment';

        $type = $this->typeResolver->resolve($isAdjustment, $sourceStoreId, $storeId);

        $note = Typer::parseNullableString($payload['note'] ?? null);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = Typer::assertArray($payload['items'] ?? []);

        $sourceStore = null;
        $destinationStore = null;

        if ($type === StockMovementTypeEnum::INCOMING) {
            $destinationStore = $this->resolveStore($user, Typer::assertInt($storeId), 'store_id');
        }

        if ($type === StockMovementTypeEnum::OUTGOING) {
            $sourceStore = $this->resolveStore($user, Typer::assertInt($sourceStoreId), 'source_store_id');
            $destinationStore = $this->resolveStore($user, Typer::assertInt($storeId), 'store_id');
        }

        if ($type === StockMovementTypeEnum::ADJUSTMENT) {
            $destinationStore = $this->resolveStore($user, Typer::assertInt($storeId), 'store_id');
        }

        $persistedStoreId = $storeId;
        $persistedSourceStoreId = $type === StockMovementTypeEnum::OUTGOING ? $sourceStoreId : null;

        return DB::transaction(function () use (
            $type,
            $persistedStoreId,
            $persistedSourceStoreId,
            $note,
            $rows,
            $user,
            $sourceStore,
            $destinationStore,
        ): StockMovement {
            $year = (int) Carbon::now()->format('Y');
            $number = StockMovementSequence::next($type, $year, $user->getKey());

            $totals = [
                'quantity' => 0,
                'value' => 0.0,
            ];

            $movement = StockMovement::query()->create([
                'user_id' => $user->getKey(),
                'number' => $number,
                'type' => $type->value,
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
                Item::scopeForUser($itemQuery, $user);
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
                    StockMovementTypeEnum::OUTGOING => $this->applyOutgoing(
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
            StockMovementTypeEnum::INCOMING, StockMovementTypeEnum::OUTGOING => [
                'item_id' => $itemId,
                'quantity' => (int) Typer::assertScalar($row['quantity'] ?? 0),
            ],
            StockMovementTypeEnum::ADJUSTMENT => [
                'item_id' => $itemId,
                'quantity_after' => Typer::parseInt($row['quantity_after'] ?? 0),
                'adjustment_reason' => Typer::assertString($row['adjustment_reason'] ?? AdjustmentReasonEnum::OTHER->value),
            ],
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
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function applyOutgoing(Store $source, Store $destination, Item $item, array $row): array
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

        $storeItem->update(['quantity' => $after]);

        return [
            'row_quantity' => null,
            'total' => \round(\abs($difference) * $item->getPurchasePrice(), 2),
            'quantity_before' => $before,
            'quantity_after' => $after,
            'quantity_difference' => $difference,
            'adjustment_reason' => Typer::assertString($row['adjustment_reason']),
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
}
