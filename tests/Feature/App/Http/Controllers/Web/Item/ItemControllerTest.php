<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StoreItem;

\test('guest is redirected from items to login', function (): void {
    $this->get('/items')->assertRedirect('/login');
});

\test('authenticated user can view the items index', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    Item::factory()->count(3)->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get('/items', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'items/Index');
    $response->assertJsonCount(3, 'props.items');
});

\test('items index supports search', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Matcha Powder']);
    Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Brown Sugar']);

    $response = $this->be($user, 'users')->get('/items?search=matcha', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.search', 'matcha');
    $items = $response->json('props.items');
    \expect($items)->toHaveCount(1);
    \expect($items[0]['title'])->toBe('Matcha Powder');
});

\test('authenticated user can view item details with movement history', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $movement = StockMovement::factory()->incoming()->create([
        'user_id' => $user->getKey(),
        'created_by' => $user->getKey(),
    ]);

    App\Models\StockMovementItem::query()->create([
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

\test('authenticated user can create an item', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();

    $response = $this->be($user, 'users')->post('/items', [
        'title' => 'Test Item',
        'sku' => 'TEST-001',
        'unit' => 'pcs',
        'purchase_price' => '9.99',
        'description' => 'Sample',
    ]);

    $response->assertRedirect();
    $item = Item::query()->where('title', 'Test Item')->first();
    \expect($item)->not->toBeNull();
    \expect($item->getSku())->toBe('TEST-001');
    \expect($item->getWarehouseQuantity())->toBe(0.0);
    \expect(StoreItem::query()->where('store_id', $warehouse->getKey())->where('item_id', $item->getKey())->exists())->toBeTrue();
});

\test('item edit does not change warehouse quantity', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 42.5,
    ]);

    $this->be($user, 'users')->put("/items/{$item->getKey()}", [
        'title' => 'Updated Title',
        'sku' => null,
        'unit' => 'g',
        'purchase_price' => '12.00',
        'description' => 'Updated',
    ])->assertRedirect();

    $item->refresh();
    \expect($item->getTitle())->toBe('Updated Title');
    \expect($item->getWarehouseQuantity())->toBe(42.5);
});

\test('cannot delete an item with stock movement history', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $movement = StockMovement::factory()->incoming()->create([
        'user_id' => $user->getKey(),
        'created_by' => $user->getKey(),
    ]);

    App\Models\StockMovementItem::query()->create([
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
        ->delete("/items/{$item->getKey()}")
        ->assertStatus(422);

    \expect(Item::query()->where('id', $item->getKey())->exists())->toBeTrue();
});

\test('can delete an item with no movement history', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $this->be($user, 'users')->delete("/items/{$item->getKey()}")->assertRedirect('/items');
    \expect(Item::query()->where('id', $item->getKey())->exists())->toBeFalse();
});
