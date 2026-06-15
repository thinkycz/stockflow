<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;

\test('cannot delete a store with inventory', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
        'warehouse_owner_id' => $warehouse->getKey(),
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    StoreItem::query()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
    ]);

    $this->be($user, 'users')
        ->delete("/stores/{$store->getKey()}")
        ->assertStatus(422);

    \expect(Store::query()->where('id', $store->getKey())->exists())->toBeTrue();
});

\test('cannot delete a store referenced by a stock movement', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
        'warehouse_owner_id' => $warehouse->getKey(),
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $movement = StockMovement::factory()->incoming()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'created_by' => $user->getKey(),
    ]);

    StockMovementItem::query()->create([
        'stock_movement_id' => $movement->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
        'total' => 10,
        'quantity_before' => 0,
        'quantity_after' => 10,
        'quantity_difference' => 10,
        'adjustment_reason' => null,
    ]);

    $this->be($user, 'users')
        ->delete("/stores/{$store->getKey()}")
        ->assertStatus(422);

    \expect(Store::query()->where('id', $store->getKey())->exists())->toBeTrue();
});

\test('cannot delete a store referenced as a source store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $sourceStore = Store::factory()->create([
        'user_id' => $user->getKey(),
        'warehouse_owner_id' => $warehouse->getKey(),
    ]);
    $destinationStore = Store::factory()->create([
        'user_id' => $user->getKey(),
        'warehouse_owner_id' => $warehouse->getKey(),
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $movement = StockMovement::factory()->outgoing($destinationStore)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $sourceStore->getKey(),
        'created_by' => $user->getKey(),
    ]);

    StockMovementItem::query()->create([
        'stock_movement_id' => $movement->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
        'total' => 5,
        'quantity_before' => 5,
        'quantity_after' => 10,
        'quantity_difference' => 5,
        'adjustment_reason' => null,
    ]);

    $this->be($user, 'users')
        ->delete("/stores/{$sourceStore->getKey()}")
        ->assertStatus(422);

    \expect(Store::query()->where('id', $sourceStore->getKey())->exists())->toBeTrue();
});

\test('can delete an empty store with no movement history', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $user->getKey(),
        'warehouse_owner_id' => $warehouse->getKey(),
    ]);

    $this->be($user, 'users')
        ->delete("/stores/{$store->getKey()}")
        ->assertRedirect('/stores');

    \expect(Store::query()->where('id', $store->getKey())->exists())->toBeFalse();
});
