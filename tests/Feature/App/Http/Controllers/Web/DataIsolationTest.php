<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('users only see their own stores', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB, $warehouseB] = \createIsolatedUserWithWarehouse();

    Store::factory()->create([
        'user_id' => $userA->getKey(),
        'name' => 'User A Store',
        'is_warehouse' => false,
    ]);

    $response = $this->be($userB, 'users')->get('/stores', $this->inertiaHeaders());

    $response->assertOk();
    $stores = $response->json('props.stores');
    \expect($stores)->toHaveCount(1);
    \expect($stores[0]['name'])->toBe('Warehouse');
    \expect($stores[0]['id'])->toBe($warehouseB->getKey());
});

\test('users cannot view another users store', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();

    $foreignStore = Store::factory()->create([
        'user_id' => $userA->getKey(),
        'name' => 'Foreign Store',
        'is_warehouse' => false,
    ]);

    $this->be($userB, 'users')
        ->get("/stores/{$foreignStore->getKey()}", $this->inertiaHeaders())
        ->assertNotFound();
});

\test('users only see their own items', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();

    Item::factory()->create([
        'user_id' => $userA->getKey(),
        'title' => 'Foreign Item',
    ]);

    $response = $this->be($userB, 'users')->get('/items', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.items'))->toHaveCount(0);
});

\test('register creates a warehouse store for the new user', function (): void {
    $response = $this->post('/register', [
        'email' => 'isolated-user@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'locale' => 'en',
    ]);

    $response->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'isolated-user@example.com')->firstOrFail();

    $warehouse = Store::query()
        ->where('user_id', $user->getKey())
        ->where('is_warehouse', true)
        ->first();

    \expect($warehouse)->not->toBeNull();
    \expect($warehouse->getName())->toBe('Warehouse');
    \expect(Store::query()->where('user_id', $user->getKey())->count())->toBe(1);
});

\test('incoming movement increases warehouse store item quantity', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 0,
    ]);

    $this->be($user, 'users')->post('/stock-movements', [
        'mode' => 'transfer',
        'store_id' => $warehouse->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 12,
        ]],
    ])->assertRedirect();

    $quantity = StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity');

    \expect((int) $quantity)->toBe(12);
});

\test('warehouse is provisioned for users created without one', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    \expect(Store::query()->where('user_id', $user->getKey())->where('is_warehouse', true)->count())->toBe(0);

    $warehouse = $user->warehouse();

    \expect($warehouse->isWarehouse())->toBeTrue();
    \expect($warehouse->getName())->toBe('Warehouse');
    \expect(Store::query()->where('user_id', $user->getKey())->where('is_warehouse', true)->count())->toBe(1);
});

\test('outgoing movement transfers stock from warehouse to destination store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $destination = Store::factory()->create([
        'user_id' => $user->getKey(),
        'name' => 'Branch',
        'is_warehouse' => false,
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 20,
    ]);

    $this->be($user, 'users')->post('/stock-movements', [
        'mode' => 'transfer',
        'source_store_id' => $warehouse->getKey(),
        'store_id' => $destination->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 7,
        ]],
    ])->assertRedirect();

    $warehouseQty = (int) StoreItem::query()
        ->where('store_id', $warehouse->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity');

    $destinationQty = (int) StoreItem::query()
        ->where('store_id', $destination->getKey())
        ->where('item_id', $item->getKey())
        ->value('quantity');

    \expect($warehouseQty)->toBe(13);
    \expect($destinationQty)->toBe(7);
});

\test('stock movement index excludes other users movements', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();
    StockMovement::factory()->incoming()->byUser($userB)->create(['user_id' => $userB->getKey()]);

    $response = $this->be($userA, 'users')->get('/stock-movements', $this->inertiaHeaders());

    \expect($response->json('props.movements'))->toBeEmpty();
});

\test('stock movement show 404s for another user', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();
    $foreign = StockMovement::factory()->incoming()->byUser($userB)->create(['user_id' => $userB->getKey()]);

    $this->be($userA, 'users')->get("/stock-movements/{$foreign->getKey()}")->assertNotFound();
});

\test('reports only show own data', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();
    StockMovement::factory()->incoming()->byUser($userB)->create(['user_id' => $userB->getKey()]);

    $response = $this->be($userA, 'users')->get('/reports', $this->inertiaHeaders());

    \expect((float) $response->json('props.inventory_value'))->toBe(0.0);
    \expect($response->json('props.store_consumption'))->toBeEmpty();
    \expect($response->json('props.most_moved'))->toBeEmpty();
});

\test('item edit 404s for another user', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();
    $foreign = Item::factory()->create(['user_id' => $userB->getKey()]);

    $this->be($userA, 'users')->get("/items/{$foreign->getKey()}/edit", $this->inertiaHeaders())->assertNotFound();
});

\test('store edit 404s for another user', function (): void {
    [$userA] = \createIsolatedUserWithWarehouse();
    [$userB] = \createIsolatedUserWithWarehouse();
    $foreign = Store::factory()->create(['user_id' => $userB->getKey()]);

    $this->be($userA, 'users')->get("/stores/{$foreign->getKey()}/edit", $this->inertiaHeaders())->assertNotFound();
});

\test('provisionWarehouse is idempotent when called twice', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    \expect(Store::query()->where('user_id', $user->getKey())->where('is_warehouse', true)->count())->toBe(0);

    $first = $user->provisionWarehouse();
    $second = $user->provisionWarehouse();

    \expect($first->getKey())->toBe($second->getKey());
    \expect(Store::query()->where('user_id', $user->getKey())->where('is_warehouse', true)->count())->toBe(1);
});
