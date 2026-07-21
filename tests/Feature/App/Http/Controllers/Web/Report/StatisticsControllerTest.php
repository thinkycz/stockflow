<?php

declare(strict_types=1);

use App\Enums\StockMovementClassificationEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use Illuminate\Support\Carbon;

\test('guest is redirected from statistics to login', function (): void {
    $this->get('/reports/statistics')->assertRedirect('/login');
});

\test('statistics separate consumption, receipts and transfers', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $other = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '4.00']);
    StoreItem::factory()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 5]);

    StockMovement::factory()->incoming()->byUser($user)->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'total_value' => 100,
        'occurred_at' => Carbon::now()->subDays(2),
    ]);
    StockMovement::factory()->transfer($store)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $other->getKey(),
        'store_id' => $store->getKey(),
        'total_value' => 60,
        'occurred_at' => Carbon::now()->subDay(),
    ]);
    $consumption = StockMovement::factory()->consumption($store)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'total_value' => 12,
        'occurred_at' => Carbon::now()->subDay(),
    ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $consumption->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 3,
        'quantity_difference' => -3,
        'classification' => StockMovementClassificationEnum::CONSUMPTION->value,
        'total' => 12,
    ]);

    $response = $this->be($user, 'users')->get(
        '/reports/statistics?store_id=' . $store->getKey(),
        $this->inertiaHeaders(),
    );

    $response->assertOk();
    $response->assertJsonPath('component', 'reports/Statistics');
    \expect((float) $response->json('props.consumption.value'))->toBe(12.0);
    \expect((float) $response->json('props.flows.receipts_value'))->toBe(100.0);
    \expect((float) $response->json('props.flows.transfer_in_value'))->toBe(60.0);
    \expect((float) $response->json('props.flows.transfer_out_value'))->toBe(0.0);
    \expect((float) $response->json('props.current_inventory.value'))->toBe(20.0);
    \expect($response->json('props.items.0.status'))->toBe('no_data');
});

\test('statistics value past consumption using its stored price snapshot', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '4.00']);
    StoreItem::factory()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 5]);
    $consumption = StockMovement::factory()->consumption($store)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'total_value' => 12,
        'occurred_at' => Carbon::now()->subDay(),
    ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $consumption->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 3,
        'quantity_difference' => -3,
        'classification' => StockMovementClassificationEnum::CONSUMPTION->value,
        'unit_cost' => 4,
        'total' => 12,
    ]);

    $item->update(['purchase_price' => '10.00']);

    $response = $this->be($user, 'users')->get(
        '/reports/statistics?store_id=' . $store->getKey(),
        $this->inertiaHeaders(),
    );

    \expect((float) $response->json('props.items.0.consumed_value'))->toBe(12.0)
        ->and((float) $response->json('props.consumption.value'))->toBe(12.0)
        ->and((float) $response->json('props.current_inventory.value'))->toBe(50.0);
});

\test('transfer never enters consumption statistics', function (): void {
    [$user, $source] = \createIsolatedUserWithWarehouse();
    $destination = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $transfer = StockMovement::factory()->transfer($destination)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $source->getKey(),
        'store_id' => $destination->getKey(),
        'type' => StockMovementTypeEnum::TRANSFER->value,
        'total_value' => 999,
        'occurred_at' => Carbon::now(),
    ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $transfer->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 100,
        'quantity_difference' => -100,
        'total' => 999,
    ]);

    $response = $this->be($user, 'users')->get(
        '/reports/statistics?store_id=' . $source->getKey(),
        $this->inertiaHeaders(),
    );

    \expect((float) $response->json('props.consumption.value'))->toBe(0.0);
    \expect((float) $response->json('props.flows.transfer_out_value'))->toBe(999.0);
});

\test('statistics clamp period_days to the supported range', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();

    $response = $this->be($user, 'users')->get('/reports/statistics?period_days=1', $this->inertiaHeaders());
    \expect($response->json('props.filters.period_days'))->toBe(7);

    $response = $this->be($user, 'users')->get('/reports/statistics?period_days=9999', $this->inertiaHeaders());
    \expect($response->json('props.filters.period_days'))->toBe(365);
});
