<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;

\test('authenticated user can view item details with movement history', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $movement = StockMovement::factory()->incoming()->create([
        'user_id' => $user->getKey(),
        'created_by' => $user->getKey(),
        'store_id' => $warehouse->getKey(),
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

    $response = $this->be($user, 'users')->get("/items/{$item->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'items/Show');
    $response->assertJsonPath('props.item.id', $item->getKey());
    $response->assertJsonCount(1, 'props.movements');
});

\test('item show filters movements to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    // Movement at the warehouse
    $warehouseMovement = StockMovement::factory()->incoming()->create([
        'user_id' => $user->getKey(),
        'created_by' => $user->getKey(),
        'store_id' => $warehouse->getKey(),
    ]);
    StockMovementItem::query()->create([
        'stock_movement_id' => $warehouseMovement->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
        'total' => 5,
        'quantity_before' => 0,
        'quantity_after' => 5,
        'quantity_difference' => 5,
        'adjustment_reason' => null,
    ]);

    // Movement at the retail store
    $retailMovement = StockMovement::factory()->incoming()->create([
        'user_id' => $user->getKey(),
        'created_by' => $user->getKey(),
        'store_id' => $retail->getKey(),
    ]);
    StockMovementItem::query()->create([
        'stock_movement_id' => $retailMovement->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 3,
        'total' => 3,
        'quantity_before' => 0,
        'quantity_after' => 3,
        'quantity_difference' => 3,
        'adjustment_reason' => null,
    ]);

    $user->setActiveStoreId($retail->getKey());

    $response = $this->be($user, 'users')->get("/items/{$item->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $movements = $response->json('props.movements');
    \expect($movements)->toHaveCount(1);
    \expect($movements[0]['store_id'])->toBe($retail->getKey());
});

\test('item show includes active store quantity', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    StoreItem::query()->create([
        'store_id' => $retail->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 15,
    ]);

    $user->setActiveStoreId($retail->getKey());

    $response = $this->be($user, 'users')->get("/items/{$item->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.active_store.id', $retail->getKey());
    $response->assertJsonPath('props.active_store.name', $retail->getName());
    $response->assertJsonPath('props.active_store.quantity', 15);
});
