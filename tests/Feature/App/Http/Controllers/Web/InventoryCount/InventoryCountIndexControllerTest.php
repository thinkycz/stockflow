<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;

\test('guest is redirected from inventory counts to login', function (): void {
    $this->get('/inventory-counts')->assertRedirect('/login');
});

\test('authenticated user can view inventory counts for the selected store', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 7,
    ]);

    $response = $this->be($user, 'users')
        ->get('/inventory-counts?store_id=' . $store->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'inventory-counts/Index');
    $response->assertJsonPath('props.store.id', $store->getKey());

    $rows = $response->json('props.rows');
    \expect($rows[0]['item_id'])->toBe($item->getKey());
    \expect($rows[0]['current'])->toBe(7);
});

\test('inventory count index falls back to first retail store when none requested', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    $response = $this->be($user, 'users')->get('/inventory-counts', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.store.id'))->toBe($retail->getKey());
});

\test('inventory count index excludes foreign stores from the list', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $foreignStore = Store::factory()->create(['user_id' => $other->getKey()]);

    $response = $this->be($user, 'users')
        ->get('/inventory-counts?store_id=' . $foreignStore->getKey(), $this->inertiaHeaders());

    // The resolver rejects the foreign store id and falls back to the
    // user's first owned retail store (the warehouse).
    \expect($response->json('props.store.id'))->toBe($warehouse->getKey());
});
