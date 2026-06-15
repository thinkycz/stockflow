<?php

declare(strict_types=1);

use App\Enums\AdjustmentReasonEnum;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Services\StockMovementService;

\test('deleting an incoming movement restores the destination quantity', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 5,
        ]],
    ], $user);

    $this->be($user, 'users')
        ->delete("/stock-movements/{$movement->getKey()}")
        ->assertRedirect('/stock-movements');

    \expect(StockMovement::query()->where('id', $movement->getKey())->exists())->toBeFalse();
    \expect((int) StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(0);
});

\test('deleting an outgoing movement restores both source and destination quantities', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

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
            'quantity' => 4,
        ]],
    ], $user);

    $this->be($user, 'users')
        ->delete("/stock-movements/{$movement->getKey()}")
        ->assertRedirect('/stock-movements');

    \expect(StockMovement::query()->where('id', $movement->getKey())->exists())->toBeFalse();
    \expect((int) StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(10);
    \expect((int) StoreItem::query()
        ->where('store_id', $retail->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(0);
});

\test('deleting an adjustment movement restores the previous quantity', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 8,
    ]);

    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'adjustment',
        'store_id' => $warehouse->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity_after' => 3,
            'adjustment_reason' => AdjustmentReasonEnum::DAMAGED->value,
        ]],
    ], $user);

    $this->be($user, 'users')
        ->delete("/stock-movements/{$movement->getKey()}")
        ->assertRedirect('/stock-movements');

    \expect(StockMovement::query()->where('id', $movement->getKey())->exists())->toBeFalse();
    \expect((int) StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(8);
});

\test('cannot delete a movement if reversal would make inventory negative', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $movement = \app(StockMovementService::class)->createMovement([
        'mode' => 'incoming',
        'store_id' => $warehouse->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 5,
        ]],
    ], $user);

    // Consume the stock so reversal would go negative.
    StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->update(['quantity' => 2]);

    $this->be($user, 'users')
        ->delete("/stock-movements/{$movement->getKey()}")
        ->assertStatus(422);

    \expect(StockMovement::query()->where('id', $movement->getKey())->exists())->toBeTrue();
    \expect((int) StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity'))->toBe(2);
});
