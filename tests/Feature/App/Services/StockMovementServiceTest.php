<?php

declare(strict_types=1);

use App\Enums\AdjustmentReasonEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\Item;
use App\Models\StockMovementItem;
use App\Models\StockMovementSequence;
use App\Models\Store;
use App\Models\StoreItem;
use App\Services\StockMovementService;
use Illuminate\Validation\ValidationException;

\test('incoming movement adds stock to the destination store and assigns the next number', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '3.50',
    ]);

    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'note' => 'Pondělní příjem',
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 4,
        ]],
    ], $user);

    \expect($movement->getType())->toBe(StockMovementTypeEnum::INCOMING);
    \expect($movement->getSourceStoreId())->toBeNull();
    \expect($movement->getNumber())->toStartWith('IN-');
    \expect($movement->getTotalQuantity())->toBe(4);
    \expect((float) $movement->getTotalValue())->toBe(14.0);
    \expect((int) StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(4);

    $row = StockMovementItem::query()->where('stock_movement_id', $movement->getKey())->first();
    \expect($row)->not->toBeNull();
    \expect($row->getQuantityBefore())->toBe(0);
    \expect($row->getQuantityAfter())->toBe(4);
    \expect($row->getQuantityDifference())->toBe(4);
    \expect($row->getAdjustmentReason())->toBeNull();

    \expect(StockMovementSequence::query()->where('user_id', $user->getKey())->count())->toBe(1);
});

\test('outgoing movement transfers stock between two stores', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'name' => 'Brno Outlet',
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '2.00',
    ]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
    ]);

    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'transfer',
        'source_store_id' => $warehouse->getKey(),
        'store_id' => $retail->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 3,
        ]],
    ], $user);

    \expect($movement->getType())->toBe(StockMovementTypeEnum::OUTGOING);
    \expect($movement->getNumber())->toStartWith('OUT-');

    \expect((int) StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(7);
    \expect((int) StoreItem::query()
        ->where('store_id', $retail->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(3);
});

\test('outgoing movement fails when source has insufficient stock', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 1,
    ]);

    \app(StockMovementService::class)->createMovement([
        'mode' => 'transfer',
        'source_store_id' => $warehouse->getKey(),
        'store_id' => $retail->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 5,
        ]],
    ], $user);
})->throws(ValidationException::class);

\test('adjustment movement records before/after quantities and reason', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 6,
    ]);

    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'adjustment',
        'store_id' => $warehouse->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity_after' => 4,
            'adjustment_reason' => AdjustmentReasonEnum::DAMAGED->value,
        ]],
    ], $user);

    \expect($movement->getType())->toBe(StockMovementTypeEnum::ADJUSTMENT);
    \expect((int) StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(4);

    $row = StockMovementItem::query()->where('stock_movement_id', $movement->getKey())->first();
    \expect($row->getQuantityBefore())->toBe(6);
    \expect($row->getQuantityAfter())->toBe(4);
    \expect($row->getQuantityDifference())->toBe(-2);
    \expect($row->getAdjustmentReason())->toBe(AdjustmentReasonEnum::DAMAGED);
});

\test('creating a movement with an unknown item fails validation', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();

    \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'items' => [[
            'item_id' => 99999,
            'quantity' => 1,
        ]],
    ], $user);
})->throws(ValidationException::class);

\test('subsequent movements of the same type increment the sequence', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $first = \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 1]],
    ], $user);

    $second = \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 1]],
    ], $user);

    \expect($first->getNumber())->not->toBe($second->getNumber());
    \expect((int) StockMovementSequence::query()->where('user_id', $user->getKey())->value('last_number'))->toBe(2);
});
