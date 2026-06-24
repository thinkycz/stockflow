<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Models\StoreItem;
use Illuminate\Support\Carbon;

\test('guest is redirected from statistics to login', function (): void {
    $this->get('/reports/statistics')->assertRedirect('/login');
});

\test('authenticated user can view statistics', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '4.00']);
    StoreItem::query()->create([
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
    ]);

    $statement = Statement::factory()->forStore($store)->forMonth(2026, 6)->create();
    StatementDay::factory()
        ->for($statement, 'statement')
        ->state([
            'date' => Carbon::now()->subDays(5)->toDateString(),
            'total' => 500.0,
        ])
        ->create();

    $incoming = Database\Factories\StockMovementFactory::new()
        ->incoming()
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'store_id' => $store->getKey(),
            'total_value' => 100,
            'total_quantity' => 10,
            'created_at' => Carbon::now()->subDays(2),
        ]);
    Database\Factories\StockMovementItemFactory::new()
        ->create([
            'stock_movement_id' => $incoming->getKey(),
            'item_id' => $item->getKey(),
            'quantity_difference' => 10,
        ]);

    $outgoing = Database\Factories\StockMovementFactory::new()
        ->outgoing($store)
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'source_store_id' => $store->getKey(),
            'total_value' => 60,
            'total_quantity' => 6,
            'created_at' => Carbon::now()->subDays(1),
        ]);
    Database\Factories\StockMovementItemFactory::new()
        ->create([
            'stock_movement_id' => $outgoing->getKey(),
            'item_id' => $item->getKey(),
            'quantity_difference' => -6,
        ]);

    $response = $this->be($user, 'users')
        ->get('/reports/statistics?store_id=' . $store->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'reports/Statistics');
    $response->assertJsonPath('props.store.id', $store->getKey());

    \expect((float) $response->json('props.sales.total'))->toBe(500.0);
    \expect((float) $response->json('props.incoming.value'))->toBe(100.0);
    \expect($response->json('props.incoming.quantity'))->toBe(10);
    \expect((float) $response->json('props.outgoing.value'))->toBe(60.0);
    \expect($response->json('props.outgoing.quantity'))->toBe(6);
    \expect((float) $response->json('props.current_inventory.value'))->toBe(20.0);
    \expect($response->json('props.top_consumed'))->not->toBeEmpty();
});

\test('statistics only show own data', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $other->getKey()]);

    $foreign = Database\Factories\StockMovementFactory::new()
        ->outgoing($otherStore)
        ->byUser($other)
        ->create([
            'user_id' => $other->getKey(),
            'source_store_id' => $otherStore->getKey(),
            'total_value' => 9999,
            'total_quantity' => 100,
        ]);

    $response = $this->be($user, 'users')
        ->get('/reports/statistics?store_id=' . $otherStore->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    \expect((float) $response->json('props.outgoing.value'))->toBe(0.0);
    \expect($response->json('props.outgoing.movements'))->toBe(0);
    unset($foreign);
});

\test('statistics clamps period_days to the supported range', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $response = $this->be($user, 'users')
        ->get('/reports/statistics?period_days=1', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.filters.period_days'))->toBe(7);

    $response = $this->be($user, 'users')
        ->get('/reports/statistics?period_days=9999', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.filters.period_days'))->toBe(365);
});
