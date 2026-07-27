<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Services\InventorySessionService;

\test('guest is redirected from stock-movements to login', function (): void {
    $this->get('/stock-movements')->assertRedirect('/login');
});

\test('authenticated user can view stock movement index', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    StockMovement::factory()->count(2)->incoming()->create([
        'user_id' => $user->getKey(),
        'created_by' => $user->getKey(),
        'store_id' => $warehouse->getKey(),
    ]);

    $response = $this->be($user, 'users')->get('/stock-movements', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'stock-movements/Index');
    $response->assertJsonCount(2, 'props.movements');
});

\test('stock movement index supports filters', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    StockMovement::factory()->incoming()->byUser($user)->create([
        'user_id' => $user->getKey(),
        'number' => 'IN-2026-0001',
    ]);
    StockMovement::factory()->outgoing(Store::factory()->create([
        'user_id' => $user->getKey(),
    ]))->byUser($user)->create([
        'user_id' => $user->getKey(),
        'number' => 'OUT-2026-0001',
    ]);

    $response = $this->be($user, 'users')->get(
        '/stock-movements?type=transfer',
        $this->inertiaHeaders(),
    );

    \expect($response->json('props.movements'))->toHaveCount(1);
    \expect($response->json('props.movements.0.type'))->toBe('transfer');
});

\test('stock movement index filters by exact source and destination stores', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $source = Store::factory()->create(['user_id' => $user->getKey()]);
    $otherSource = Store::factory()->create(['user_id' => $user->getKey()]);
    $destination = Store::factory()->create(['user_id' => $user->getKey()]);
    $otherDestination = Store::factory()->create(['user_id' => $user->getKey()]);

    $matching = StockMovement::factory()->outgoing($destination)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $source->getKey(),
    ]);
    StockMovement::factory()->outgoing($destination)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $otherSource->getKey(),
    ]);
    StockMovement::factory()->outgoing($otherDestination)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $source->getKey(),
    ]);

    $response = $this->be($user, 'users')->get(
        '/stock-movements?source_store_id=' . $source->getKey()
            . '&destination_store_id=' . $destination->getKey(),
        $this->inertiaHeaders(),
    );

    $response->assertOk();
    $response->assertJsonCount(1, 'props.movements');
    $response->assertJsonPath('props.movements.0.id', $matching->getKey());
    $response->assertJsonPath('props.filters.source_store_id', $source->getKey());
    $response->assertJsonPath('props.filters.destination_store_id', $destination->getKey());
});

\test('stock movement index provides all owned stores as filter options', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $inactiveStore = Store::factory()->inactive()->create([
        'user_id' => $user->getKey(),
        'name' => 'Closed branch',
    ]);
    Store::factory()->create(['name' => 'Foreign branch']);

    $response = $this->be($user, 'users')->get('/stock-movements', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonCount(2, 'props.stores');
    $response->assertJsonPath('props.stores.0.id', $inactiveStore->getKey());
    $response->assertJsonPath('props.stores.0.name', 'Closed branch');
    $response->assertJsonPath('props.stores.1.id', $warehouse->getKey());
});

\test('stock movement index exposes signed net value for inventory reconciliation', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create([
        'user_id' => $user->getKey(),
        'purchase_price' => 10,
    ]);
    StoreItem::query()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
    ]);
    \app(InventorySessionService::class)->createSession($user, $store, [
        ['item_id' => $item->getKey(), 'quantity' => 7],
    ]);

    $response = $this->be($user, 'users')->get('/stock-movements', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.movements.0.type', 'inventory_reconciliation');
    $response->assertJsonPath('props.movements.0.total_value', 30);
    $response->assertJsonPath('props.movements.0.net_value', -30);
});
