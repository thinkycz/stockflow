<?php

declare(strict_types=1);

use App\Models\InventoryCount;
use App\Models\Item;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use App\Services\InventoryCountService;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-24 10:00:00'));
});

\afterEach(function (): void {
    Carbon::setTestNow();
});

\test('recordCounts persists snapshots and updates store_items quantities', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $a = Item::factory()->create(['user_id' => $user->getKey()]);
    $b = Item::factory()->create(['user_id' => $user->getKey()]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);

    $service->recordCounts($user, $store, [
        ['item_id' => $a->getKey(), 'quantity' => 12, 'note' => 'morning'],
        ['item_id' => $b->getKey(), 'quantity' => 0, 'note' => null],
    ]);

    \expect(InventoryCount::query()->where('store_id', $store->getKey())->count())->toBe(2);

    \expect($a->id)->not->toBeNull();
    $row = StoreItem::query()
        ->where('store_id', $store->getKey())
        ->where('item_id', $a->getKey())
        ->first();
    \expect($row)->not->toBeNull();
    \expect($row->getQuantity())->toBe(12);
});

\test('recordCounts ignores items owned by another user', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $foreign = Item::factory()->create(['user_id' => $other->getKey()]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);

    $service->recordCounts($user, $store, [
        ['item_id' => $foreign->getKey(), 'quantity' => 5],
    ]);

    \expect(InventoryCount::query()->where('item_id', $foreign->getKey())->count())->toBe(0);
});

\test('consumptionLastDays sums negative differences from outgoing movements only', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    // Incoming movement should not count as consumption.
    $incoming = Database\Factories\StockMovementFactory::new()
        ->incoming()
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'store_id' => $retail->getKey(),
            'created_at' => Carbon::now()->subDays(5),
        ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $incoming->getKey(),
        'item_id' => $item->getKey(),
        'quantity_difference' => 20,
    ]);

    // Outgoing movement in the window — contributes to consumption.
    $outgoing = Database\Factories\StockMovementFactory::new()
        ->outgoing($retail)
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'source_store_id' => $retail->getKey(),
            'created_at' => Carbon::now()->subDays(10),
        ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $outgoing->getKey(),
        'item_id' => $item->getKey(),
        'quantity_difference' => -5,
    ]);

    // Outgoing movement outside the window — should be ignored.
    $oldOutgoing = Database\Factories\StockMovementFactory::new()
        ->outgoing($retail)
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'source_store_id' => $retail->getKey(),
            'created_at' => Carbon::now()->subDays(40),
        ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $oldOutgoing->getKey(),
        'item_id' => $item->getKey(),
        'quantity_difference' => -100,
    ]);

    // Outgoing movement from the warehouse — should not count for the retail store.
    $warehouseOutgoing = Database\Factories\StockMovementFactory::new()
        ->outgoing($warehouse)
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'source_store_id' => $warehouse->getKey(),
            'created_at' => Carbon::now()->subDays(2),
        ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $warehouseOutgoing->getKey(),
        'item_id' => $item->getKey(),
        'quantity_difference' => -50,
    ]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);
    $consumption = $service->consumptionLastDays($retail, $item, 30);

    \expect($consumption['quantity'])->toBe(5);
    \expect((float) $consumption['per_day'])->toBe(5 / 30);
});

\test('predictedRunOut returns days_left based on current quantity and consumption', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    StoreItem::query()->create([
        'store_id' => $retail->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 15,
    ]);

    $outgoing = Database\Factories\StockMovementFactory::new()
        ->outgoing($retail)
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'source_store_id' => $retail->getKey(),
            'created_at' => Carbon::now()->subDays(2),
        ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $outgoing->getKey(),
        'item_id' => $item->getKey(),
        'quantity_difference' => -30,
    ]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);
    $prediction = $service->predictedRunOut($retail, $item, 30);

    \expect($prediction['current'])->toBe(15);
    \expect($prediction['status'])->toBe('ok');
    \expect((float) $prediction['per_day'])->toBe(1.0);
    \expect($prediction['days_left'])->toBe(15);
});

\test('predictedRunOut flags out of stock as out', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);
    $prediction = $service->predictedRunOut($retail, $item, 30);

    \expect($prediction['current'])->toBe(0);
    \expect($prediction['status'])->toBe('out');
});

\test('predictedRunOut returns no_data status when there is no consumption', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    StoreItem::query()->create([
        'store_id' => $retail->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 20,
    ]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);
    $prediction = $service->predictedRunOut($retail, $item, 30);

    \expect($prediction['status'])->toBe('no_data');
    \expect($prediction['days_left'])->toBeNull();
});

\test('buildStoreView orders rows by restock urgency', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $critical = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Critical Item']);
    $low = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Low Item']);
    $ok = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Ok Item']);

    StoreItem::query()->create(['store_id' => $retail->getKey(), 'item_id' => $critical->getKey(), 'quantity' => 0]);
    StoreItem::query()->create(['store_id' => $retail->getKey(), 'item_id' => $low->getKey(), 'quantity' => 5]);
    StoreItem::query()->create(['store_id' => $retail->getKey(), 'item_id' => $ok->getKey(), 'quantity' => 100]);

    $outgoing = Database\Factories\StockMovementFactory::new()
        ->outgoing($retail)
        ->byUser($user)
        ->create([
            'user_id' => $user->getKey(),
            'source_store_id' => $retail->getKey(),
            'created_at' => Carbon::now()->subDays(1),
        ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $outgoing->getKey(),
        'item_id' => $low->getKey(),
        'quantity_difference' => -60,
    ]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $outgoing->getKey(),
        'item_id' => $ok->getKey(),
        'quantity_difference' => -10,
    ]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);
    $view = $service->buildStoreView($user, $retail);

    $statuses = \array_map(static fn(array $row): string => $row['status'], $view);

    \expect($statuses[0])->toBe('out');
    \expect(\in_array('soon', $statuses, true))->toBeTrue();
    \expect(\end($statuses))->toBe('ok');
});

\test('historyForUser returns snapshots in descending counted_at order', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    InventoryCount::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
        'counted_at' => Carbon::now()->subDays(3),
    ]);
    InventoryCount::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 8,
        'counted_at' => Carbon::now()->subDays(2),
    ]);
    InventoryCount::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
        'counted_at' => Carbon::now()->subDay(),
    ]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);
    $rows = $service->historyForUser(
        $user,
        $store,
        null,
        Carbon::now()->subDays(7),
        Carbon::now(),
        100,
    );

    \expect($rows)->toHaveCount(3);
    \expect($rows[0]['quantity'])->toBe(5);
    \expect($rows[2]['quantity'])->toBe(10);
});

\test('historyForUser excludes counts outside the date range', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    InventoryCount::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 7,
        'counted_at' => Carbon::now()->subDays(60),
    ]);
    InventoryCount::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 3,
        'counted_at' => Carbon::now()->subDays(2),
    ]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);
    $rows = $service->historyForUser(
        $user,
        $store,
        null,
        Carbon::now()->subDays(30),
        Carbon::now(),
        100,
    );

    \expect($rows)->toHaveCount(1);
    \expect($rows[0]['quantity'])->toBe(3);
});

\test('sparklineForItem densifies missing days as null', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    InventoryCount::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 12,
        'counted_at' => Carbon::now()->subDays(5),
    ]);
    InventoryCount::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 7,
        'counted_at' => Carbon::now()->subDay(),
    ]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);
    $sparkline = $service->sparklineForItem($user, $store, $item, 10);

    \expect($sparkline)->toHaveCount(10);
    \expect($sparkline[0]['value'])->toBeNull();
    // Two snapshots land on the right days.
    $filled = \array_values(\array_filter(
        $sparkline,
        static fn(array $row): bool => $row['value'] !== null,
    ));
    \expect($filled)->toHaveCount(2);
});

\test('sparklineForItem keeps only the latest count for a given day', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    InventoryCount::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 1,
        'counted_at' => Carbon::now()->subDays(1)->setTime(8, 0),
    ]);
    InventoryCount::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 99,
        'counted_at' => Carbon::now()->subDays(1)->setTime(20, 30),
    ]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);
    $sparkline = $service->sparklineForItem($user, $store, $item, 5);

    // The 8:00 entry must be overwritten by the 20:30 entry on the same day.
    $entry = \array_values(\array_filter(
        $sparkline,
        static fn(array $row): bool => $row['value'] !== null,
    ));
    \expect($entry)->toHaveCount(1);
    \expect($entry[0]['value'])->toBe(99);
});

\test('buildStoreView attaches a sparkline per row', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    InventoryCount::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $store->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 4,
        'counted_at' => Carbon::now()->subDays(2),
    ]);

    $service = Typer::assertInstance(\app(InventoryCountService::class), InventoryCountService::class);
    $view = $service->buildStoreView($user, $store);

    \expect($view[0]['sparkline'])->toHaveCount(30);
    \expect($view[0]['sparkline'][0]['value'])->toBeNull();
});
