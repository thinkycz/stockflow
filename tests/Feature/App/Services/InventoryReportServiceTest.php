<?php

declare(strict_types=1);

use App\Domain\Inventory\InventoryReportService;
use App\Enums\StockMovementClassificationEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Resolver;

\test('inventory report reconstructs month end quantities and values them at current prices', function (): void {
    Carbon::setTestNow('2026-08-15 12:00:00');
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '4.00']);
    StoreItem::factory()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 10]);

    $incoming = StockMovement::factory()->incoming()->byUser($user)->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'occurred_at' => '2026-08-05 10:00:00',
        'total_value' => 20,
    ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $incoming->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
        'quantity_difference' => 5,
        'total' => 20,
    ]);

    $consumption = StockMovement::factory()->consumption($store)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'occurred_at' => '2026-08-10 10:00:00',
        'total_value' => 8,
    ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $consumption->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 2,
        'quantity_difference' => -2,
        'classification' => StockMovementClassificationEnum::CONSUMPTION->value,
        'total' => 8,
    ]);

    $report = Resolver::resolve(InventoryReportService::class)->build(
        $user,
        $store,
        Carbon::parse('2026-07-01 00:00:00'),
        Carbon::parse('2026-07-31 23:59:59'),
    );

    \expect((float) $report['items'][0]['current_quantity'])->toBe(7.0)
        ->and((float) $report['current_inventory']['value'])->toBe(28.0)
        ->and($report['current_inventory']['value_is_estimate'])->toBeTrue();
});

\test('inventory report forecasts use only observations available at the cutoff', function (): void {
    Carbon::setTestNow('2026-08-15 12:00:00');
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::factory()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 10]);

    $available = InventorySession::factory()->forStore($store)->create(['counted_at' => '2026-07-01 00:00:00']);
    InventorySessionItem::factory()->create([
        'session_id' => $available->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
        'observation_started_at' => '2026-06-01 00:00:00',
        'quantity_difference' => -10,
        'classification' => StockMovementClassificationEnum::CONSUMPTION->value,
    ]);
    $future = InventorySession::factory()->forStore($store)->create(['counted_at' => '2026-08-10 00:00:00']);
    InventorySessionItem::factory()->create([
        'session_id' => $future->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 0,
        'observation_started_at' => '2026-07-01 00:00:00',
        'quantity_difference' => -100,
        'classification' => StockMovementClassificationEnum::CONSUMPTION->value,
    ]);

    $report = Resolver::resolve(InventoryReportService::class)->build(
        $user,
        $store,
        Carbon::parse('2026-07-01 00:00:00'),
        Carbon::parse('2026-07-31 23:59:59'),
    );

    \expect((float) $report['items'][0]['avg_daily_consumption'])->toBeLessThan(1.0)
        ->and($report['items'][0]['projected_stockout_at'])->toBe('2026-08-29');
});

\test('inventory report reconstructs both transfer perspectives across a later reversal', function (): void {
    Carbon::setTestNow('2026-08-15 12:00:00');
    [$user] = \createIsolatedUserWithWarehouse();
    $source = Store::factory()->create(['user_id' => $user->getKey()]);
    $destination = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::factory()->create(['store_id' => $source->getKey(), 'item_id' => $item->getKey(), 'quantity' => 7]);
    StoreItem::factory()->create(['store_id' => $destination->getKey(), 'item_id' => $item->getKey(), 'quantity' => 3]);

    $transfer = StockMovement::factory()->transfer($destination)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $source->getKey(),
        'occurred_at' => '2026-07-20 10:00:00',
        'total_value' => 16,
        'reversed_at' => '2026-08-05 10:00:00',
    ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $transfer->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 4,
        'quantity_difference' => -4,
        'total' => 16,
    ]);
    $reversal = StockMovement::factory()->byUser($user)->create([
        'user_id' => $user->getKey(),
        'type' => StockMovementTypeEnum::REVERSAL->value,
        'source_store_id' => $source->getKey(),
        'store_id' => $destination->getKey(),
        'reversal_of_id' => $transfer->getKey(),
        'occurred_at' => '2026-08-05 10:00:00',
        'total_value' => -16,
    ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $reversal->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 4,
        'quantity_difference' => 4,
        'total' => -16,
    ]);

    $service = Resolver::resolve(InventoryReportService::class);
    $start = Carbon::parse('2026-07-01 00:00:00');
    $cutoff = Carbon::parse('2026-07-31 23:59:59');

    \expect((float) $service->build($user, $source, $start, $cutoff)['items'][0]['current_quantity'])->toBe(3.0)
        ->and((float) $service->build($user, $destination, $start, $cutoff)['items'][0]['current_quantity'])->toBe(7.0);
});

\test('inventory report keeps a monthly movement until the later reversal cutoff', function (): void {
    Carbon::setTestNow('2026-08-15 12:00:00');
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::factory()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 5]);
    StockMovement::factory()->incoming()->byUser($user)->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'occurred_at' => '2026-07-10 10:00:00',
        'reversed_at' => '2026-08-02 10:00:00',
        'total_value' => 100,
    ]);
    $consumption = StockMovement::factory()->consumption($store)->byUser($user)->create([
        'user_id' => $user->getKey(),
        'occurred_at' => '2026-07-12 10:00:00',
        'total_value' => 12,
    ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $consumption->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 3,
        'quantity_difference' => -3,
        'classification' => StockMovementClassificationEnum::CONSUMPTION->value,
        'total' => 12,
    ]);

    $report = Resolver::resolve(InventoryReportService::class)->build(
        $user,
        $store,
        Carbon::parse('2026-07-01 00:00:00'),
        Carbon::parse('2026-07-31 23:59:59'),
    );

    \expect((float) $report['flows']['receipts_value'])->toBe(100.0)
        ->and((float) $report['items'][0]['consumed_quantity'])->toBe(3.0);
});
