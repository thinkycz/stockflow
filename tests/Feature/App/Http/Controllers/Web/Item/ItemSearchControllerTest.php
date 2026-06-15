<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;

\test('search returns matching items scoped to the authenticated user', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    Item::factory()->create([
        'user_id' => $user->getKey(),
        'title' => 'Blue widget',
        'sku' => 'BW-001',
    ]);
    Item::factory()->create([
        'user_id' => $user->getKey(),
        'title' => 'Red gadget',
        'sku' => 'RG-001',
    ]);
    Item::factory()->create([
        'user_id' => $user->getKey(),
        'title' => 'Green sprocket',
        'sku' => 'GS-001',
    ]);

    $response = $this->be($user, 'users')->get('/items/search?q=widget');

    $response->assertOk();
    $response->assertJsonPath('items.0.title', 'Blue widget');
    \expect($response->json('items'))->toHaveCount(1);
});

\test('search returns multiple matches', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    Item::factory()->create([
        'user_id' => $user->getKey(),
        'title' => 'Blue widget',
    ]);
    Item::factory()->create([
        'user_id' => $user->getKey(),
        'title' => 'Red widget',
    ]);
    Item::factory()->create([
        'user_id' => $user->getKey(),
        'title' => 'Green gadget',
    ]);

    $response = $this->be($user, 'users')->get('/items/search?q=widget');

    $response->assertOk();
    \expect($response->json('items'))->toHaveCount(2);
});

\test('search matches against sku', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    Item::factory()->create([
        'user_id' => $user->getKey(),
        'title' => 'Mystery item',
        'sku' => 'UNIQUE-SKU-123',
    ]);

    $response = $this->be($user, 'users')->get('/items/search?q=unique-sku');

    $response->assertOk();
    \expect($response->json('items.0.sku'))->toBe('UNIQUE-SKU-123');
});

\test('search excludes other users items', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    [$otherUser, $otherWarehouse] = \createIsolatedUserWithWarehouse();
    Item::factory()->create([
        'user_id' => $otherUser->getKey(),
        'title' => 'Other user widget',
    ]);

    $response = $this->be($user, 'users')->get('/items/search?q=widget');

    $response->assertOk();
    \expect($response->json('items'))->toHaveCount(0);
});

\test('search returns empty array for empty query', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    Item::factory()->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get('/items/search');

    $response->assertOk();
    \expect($response->json('items'))->toHaveCount(0);
});

\test('search includes warehouse quantity and per-store quantities', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'title' => 'Tracked widget',
    ]);

    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 12,
    ]);
    StoreItem::query()->create([
        'store_id' => $retail->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 3,
    ]);

    $response = $this->be($user, 'users')->get('/items/search?q=tracked');

    $response->assertOk();
    $data = $response->json('items.0');
    \expect((float) $data['warehouse_quantity'])->toBe(12.0);
    \expect((float) $data['quantities_by_store'][(string) $retail->getKey()])->toBe(3.0);
});
