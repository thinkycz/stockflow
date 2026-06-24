<?php

declare(strict_types=1);

use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;

\test('update controller creates a session, persists rows, and updates store items', function (): void {
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

    $session = InventorySession::query()->where('store_id', $store->getKey())->first();
    \expect($session)->not->toBeNull();

    $row = InventorySessionItem::query()
        ->where('session_id', $session->getKey())
        ->where('item_id', $item->getKey())
        ->first();
    \expect($row)->not->toBeNull();
    \expect($row->getQuantity())->toBe(9);

    $storeItem = StoreItem::query()
        ->where('store_id', $store->getKey())
        ->where('item_id', $item->getKey())
        ->first();
    \expect($storeItem)->not->toBeNull();
    \expect($storeItem->getQuantity())->toBe(9);

    $response->assertRedirect(\route('inventory-counts.show', ['session' => $session->getKey()]));
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
                ['item_id' => $item->getKey(), 'quantity' => -3],
            ],
        ])
        ->assertStatus(422);
});
