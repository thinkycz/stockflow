<?php

declare(strict_types=1);

use App\Models\InventoryCount;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('update controller persists counts and updates store items', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 1,
    ]);

    $response = $this->be($user, 'users')
        ->post('/inventory-counts', [
            'store_id' => $store->getKey(),
            'rows' => [
                ['item_id' => $item->getKey(), 'quantity' => 9, 'note' => 'manual'],
            ],
        ]);

    $response->assertRedirect();
    \assertInertiaFlash($response, 'success', \__('Inventory count saved.'));

    \expect(InventoryCount::query()
        ->where('store_id', $store->getKey())
        ->where('item_id', $item->getKey())
        ->count())->toBe(1);

    $storeItem = StoreItem::query()
        ->where('store_id', $store->getKey())
        ->where('item_id', $item->getKey())
        ->first();
    \expect($storeItem)->not->toBeNull();
    \expect($storeItem->getQuantity())->toBe(9);
});

\test('update controller rejects another users store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $foreignStore = Store::factory()->create(['user_id' => $other->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/inventory-counts', [
            'store_id' => $foreignStore->getKey(),
            'rows' => [
                ['item_id' => $item->getKey(), 'quantity' => 1],
            ],
        ])
        ->assertStatus(422);
});

\test('update controller rejects another users item', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $foreignItem = Item::factory()->create(['user_id' => $other->getKey()]);

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/inventory-counts', [
            'store_id' => $store->getKey(),
            'rows' => [
                ['item_id' => $foreignItem->getKey(), 'quantity' => 1],
            ],
        ])
        ->assertStatus(422);
});

\test('update controller requires at least one row', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/inventory-counts', [
            'store_id' => $store->getKey(),
            'rows' => [],
        ])
        ->assertStatus(422);
});

\test('update controller rejects negative quantity', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $this->be($user, 'users')
        ->withHeaders($this->inertiaHeaders())
        ->post('/inventory-counts', [
            'store_id' => $store->getKey(),
            'rows' => [
                ['item_id' => $item->getKey(), 'quantity' => -1],
            ],
        ])
        ->assertStatus(422);
});

\test('limited user cannot update inventory for a non-assigned store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $admin->update(['is_admin' => true, 'parent_user_id' => null, 'assigned_store_id' => null]);

    $own = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $other = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);

    $limited = Typer::assertInstance(UserFactory::new()->limited($own)->createOne(), User::class);
    $item = Item::factory()->create(['user_id' => $admin->getKey()]);

    $this->be($limited, 'users')
        ->post('/inventory-counts', [
            'store_id' => $other->getKey(),
            'rows' => [
                ['item_id' => $item->getKey(), 'quantity' => 3],
            ],
        ])
        ->assertForbidden();

    \expect(InventoryCount::query()->where('store_id', $other->getKey())->count())->toBe(0);
});

\test('limited user can update inventory for their assigned store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $admin->update(['is_admin' => true, 'parent_user_id' => null, 'assigned_store_id' => null]);

    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $item = Item::factory()->create(['user_id' => $admin->getKey()]);

    $response = $this->be($limited, 'users')
        ->post('/inventory-counts', [
            'store_id' => $store->getKey(),
            'rows' => [
                ['item_id' => $item->getKey(), 'quantity' => 6, 'note' => 'evening'],
            ],
        ]);

    $response->assertRedirect();
    \expect(InventoryCount::query()
        ->where('store_id', $store->getKey())
        ->where('user_id', $admin->getKey())
        ->where('created_by', $limited->getKey())
        ->count())->toBe(1);
});
