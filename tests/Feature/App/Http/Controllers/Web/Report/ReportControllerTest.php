<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;

\test('guest is redirected from reports to login', function (): void {
    $this->get('/reports')->assertRedirect('/login');
});

\test('authenticated user can view reports', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'reports/Index');
    $response->assertJsonStructure([
        'props' => [
            'inventory_value',
            'monthly' => ['incoming', 'outgoing'],
            'store_consumption',
            'most_moved',
            'adjustments',
            'reasons',
        ],
    ]);
});

\test('reports only show own data', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    StockMovement::factory()->incoming()->byUser($other)->create(['user_id' => $other->getKey()]);

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    \expect((float) $response->json('props.inventory_value'))->toBe(0.0);
});

\test('store consumption counts outgoing movements from a retail source store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'name' => 'Branch North',
    ]);
    $destination = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'name' => 'Branch East',
    ]);

    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '5.00',
    ]);

    StoreItem::query()->create([
        'store_id' => $retail->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
    ]);

    $this->be($user, 'users')->post('/stock-movements', [
        'mode' => 'transfer',
        'source_store_id' => $retail->getKey(),
        'store_id' => $destination->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 4,
        ]],
    ])->assertRedirect();

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    $response->assertOk();

    $consumption = $response->json('props.store_consumption');

    $row = \array_values(\array_filter(
        $consumption,
        static fn(array $row): bool => $row['store_id'] === $retail->getKey(),
    ))[0] ?? null;

    \expect($row)->not->toBeNull();
    \expect($row['movements_count'])->toBe(1);
    \expect((float) $row['total_quantity'])->toBe(4.0);
    \expect((float) $row['total_value'])->toBe(20.0);
});

\test('store consumption ignores outgoing movements where the source is a warehouse', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
        'name' => 'Branch West',
    ]);

    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => '3.00',
    ]);

    StoreItem::query()->create([
        'store_id' => $warehouse->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
    ]);

    // Outgoing from the warehouse to the retail store. The retail
    // store is the `store_id` (destination) on this movement, not
    // the `source_store_id`. The pre-fix report controller would have
    // counted this as the retail store's "consumption".
    $this->be($user, 'users')->post('/stock-movements', [
        'mode' => 'transfer',
        'source_store_id' => $warehouse->getKey(),
        'store_id' => $retail->getKey(),
        'items' => [[
            'item_id' => $item->getKey(),
            'quantity' => 5,
        ]],
    ])->assertRedirect();

    $response = $this->be($user, 'users')->get('/reports', $this->inertiaHeaders());

    $response->assertOk();

    $consumption = $response->json('props.store_consumption');

    $row = \array_values(\array_filter(
        $consumption,
        static fn(array $row): bool => $row['store_id'] === $retail->getKey(),
    ))[0] ?? null;

    // The retail store sent nothing out, so its consumption is zero.
    \expect($row)->not->toBeNull();
    \expect($row['movements_count'])->toBe(0);
    \expect((float) $row['total_quantity'])->toBe(0.0);
    \expect((float) $row['total_value'])->toBe(0.0);
});
