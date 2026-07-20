<?php

declare(strict_types=1);

use App\Models\InventorySession;
use App\Models\Item;
use App\Models\Store;
use Illuminate\Support\Carbon;

\test('guest is redirected from stores to login', function (): void {
    $this->get('/stores')->assertRedirect('/login');
});

\test('authenticated user can view stores index', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    Store::factory()->count(3)->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get('/stores', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stores/Index');
});

\test('stores index supports search', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    Store::factory()->create(['user_id' => $user->getKey(), 'name' => 'Alpha']);
    Store::factory()->create(['user_id' => $user->getKey(), 'name' => 'Beta']);

    $response = $this->be($user, 'users')->get('/stores?search=alpha', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.search', 'alpha');
    \expect($response->json('props.stores'))->toHaveCount(1);
});

\test('stores index excludes other users stores', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    Store::factory()->create(['user_id' => $other->getKey(), 'name' => 'Other Store']);

    $response = $this->be($user, 'users')->get('/stores', $this->inertiaHeaders());

    $names = \array_column($response->json('props.stores'), 'name');
    \expect($names)->not->toContain('Other Store');
});

\test('per-store metrics show current inventory risk and data freshness', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'name' => 'Branch South',
    ]);
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '4.00',
    ]);
    $countedAt = Carbon::parse('2026-07-18 09:00:00');
    InventorySession::factory()->forStore($retail)->byUser($user)->create([
        'status' => 'closed',
        'counted_at' => $countedAt,
    ]);

    // 1 incoming purchase to the retail store. The controller only
    // exposes 'transfer' / 'adjustment' as modes; a transfer with
    // no source_store_id resolves to type=INCOMING in the service.
    $this->be($user, 'users')->post('/stock-movements', [
        'mode' => 'transfer',
        'store_id' => $retail->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 5]],
    ])->assertRedirect();

    // 1 outgoing transfer from the retail store to the warehouse.
    // The incoming above seeded the retail with 5 units.
    $this->be($user, 'users')->post('/stock-movements', [
        'mode' => 'transfer',
        'source_store_id' => $retail->getKey(),
        'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 2]],
    ])->assertRedirect();

    $response = $this->be($user, 'users')->get('/stores', $this->inertiaHeaders());

    $row = \array_values(\array_filter(
        $response->json('props.stores'),
        static fn(array $r): bool => $r['id'] === $retail->getKey(),
    ))[0] ?? null;

    \expect($row)->not->toBeNull();
    \expect((float) $row['inventory_value'])->toBe(12.0)
        ->and($row['sku_count'])->toBe(1)
        ->and($row['out_of_stock'])->toBe(0)
        ->and($row['risk_count'])->toBe(0)
        ->and($row['last_inventory_at'])->toBe($countedAt->toDateTimeString());
});

\test('per-store metrics only count the authenticated users own movements', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    [$other, $otherWarehouse] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '1.00',
    ]);
    $otherItem = Item::factory()->create([
        'user_id' => $other->getKey(),
        'purchase_price' => '1.00',
    ]);

    // Movements owned by the OTHER user must not affect this user's metrics.
    // The controller only exposes 'transfer' / 'adjustment' as modes; a transfer
    // with no source_store_id is treated as an incoming movement.
    $this->be($other, 'users')->post('/stock-movements', [
        'mode' => 'transfer',
        'store_id' => $otherWarehouse->getKey(),
        'items' => [['item_id' => $otherItem->getKey(), 'quantity' => 10]],
    ])->assertRedirect();

    // The other company cannot affect this company's inventory metrics.
    $response = $this->be($user, 'users')->get('/stores', $this->inertiaHeaders());

    foreach ($response->json('props.stores') as $row) {
        \expect((float) $row['inventory_value'])->toBe(0.0);
        \expect($row['sku_count'])->toBe(0);
        \expect($row['out_of_stock'])->toBe(0);
        \expect($row['risk_count'])->toBe(0);
    }
});
