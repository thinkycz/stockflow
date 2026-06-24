<?php

declare(strict_types=1);

use App\Enums\ItemStockStatusEnum;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use Illuminate\Support\Carbon;

\test('store show page is reachable', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get("/stores/{$store->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stores/Show');
    $response->assertJsonPath('props.store.id', $store->getKey());
});

\test('store show 404s for another user', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $other->getKey()]);

    $this->be($user, 'users')->get("/stores/{$otherStore->getKey()}")->assertNotFound();
});

\test('store show inventory exposes status, sparkline and last count per item', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => 12.5]);
    StoreItem::factory()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 0,
    ]);

    $response = $this->be($user, 'users')->get("/stores/{$store->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $inventory = $response->json('props.inventory');
    \expect($inventory)->toHaveCount(1);
    $row = $inventory[0];
    \expect($row)->toHaveKey('item_id', $item->getKey());
    \expect($row)->toHaveKey('quantity', 0);
    \expect($row)->toHaveKey('total_value', 0.0);
    \expect($row)->toHaveKey('status', ItemStockStatusEnum::OUT_OF_STOCK->value);
    \expect($row)->toHaveKey('sparkline');
    \expect($row['sparkline'])->toBeArray();
    \expect($row)->toHaveKey('last_count_at', null);
    \expect($response->json('props.now'))->toBeString();
});

\test('store show inventory status reflects current quantity bucket', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::factory()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
    ]);

    $response = $this->be($user, 'users')->get("/stores/{$store->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $row = $response->json('props.inventory.0');
    \expect($row['status'])->toBe(ItemStockStatusEnum::fromQuantity(5)->value);
    \expect($row['last_count_at'])->toBeNull();
});

\test('store show now prop is a current timestamp', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $before = Carbon::now()->subSecond();

    $response = $this->be($user, 'users')->get("/stores/{$store->getKey()}", $this->inertiaHeaders());

    $response->assertOk();
    $now = Carbon::parse($response->json('props.now'));
    \expect($now->greaterThanOrEqualTo($before))->toBeTrue();
    \expect($now->lessThanOrEqualTo(Carbon::now()->addSecond()))->toBeTrue();
});
