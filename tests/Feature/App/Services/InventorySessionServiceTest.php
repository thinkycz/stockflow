<?php

declare(strict_types=1);

use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use App\Services\InventorySessionService;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-24 10:00:00'));
});

\afterEach(function (): void {
    Carbon::setTestNow();
});

\test('createSession persists a session with rows and updates store_items', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $a = Item::factory()->create(['user_id' => $user->getKey()]);
    $b = Item::factory()->create(['user_id' => $user->getKey()]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);

    $session = $service->createSession($user, $store, [
        ['item_id' => $a->getKey(), 'quantity' => 12, 'note' => 'morning'],
        ['item_id' => $b->getKey(), 'quantity' => 0, 'note' => null],
    ], 'Opening counts');

    \expect(InventorySession::query()->where('store_id', $store->getKey())->count())->toBe(1);
    \expect($session->getNote())->toBe('Opening counts');
    \expect($session->getCreatedBy())->toBe($user->getKey());
    \expect(InventorySessionItem::query()->where('session_id', $session->getKey())->count())->toBe(2);

    $row = StoreItem::query()
        ->where('store_id', $store->getKey())
        ->where('item_id', $a->getKey())
        ->first();
    \expect($row)->not->toBeNull();
    \expect($row->getQuantity())->toBe(12);
});

\test('createSession ignores items owned by another user', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $foreign = Item::factory()->create(['user_id' => $other->getKey()]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $session = $service->createSession($user, $store, [
        ['item_id' => $foreign->getKey(), 'quantity' => 5],
    ]);

    \expect(InventorySessionItem::query()->where('session_id', $session->getKey())->count())->toBe(0);
    \expect(StoreItem::query()->where('item_id', $foreign->getKey())->count())->toBe(0);
});

\test('previousQuantity returns null when no prior session exists', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    \expect($service->previousQuantity($store, $item))->toBeNull();
});

\test('previousQuantity returns the most recent count and respects a $before cutoff', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $old = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'counted_at' => Carbon::now()->subDays(5),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $old->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 7,
    ]);
    $newer = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'counted_at' => Carbon::now()->subDay(),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $newer->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 12,
    ]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    \expect($service->previousQuantity($store, $item))->toBe(12);
    \expect($service->previousQuantity($store, $item, Carbon::now()->subDays(2)))->toBe(7);
});

\test('buildStoreView sorts items alphabetically by title and exposes previous quantity', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $zeta = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Zeta Item']);
    $alpha = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Alpha Item']);
    $middle = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Middle Item']);

    $old = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'counted_at' => Carbon::now()->subDay(),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $old->getKey(),
        'item_id' => $alpha->getKey(),
        'quantity' => 4,
    ]);

    StoreItem::query()->create([
        'store_id' => $store->getKey(),
        'item_id' => $zeta->getKey(),
        'quantity' => 9,
    ]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $view = $service->buildStoreView($user, $store);

    \expect($view)->toHaveCount(3);
    \expect($view[0]['title'])->toBe('Alpha Item');
    \expect($view[0]['current'])->toBe(0);
    \expect($view[0]['previous'])->toBe(4);
    \expect($view[1]['title'])->toBe('Middle Item');
    \expect($view[1]['current'])->toBe(0);
    \expect($view[1]['previous'])->toBeNull();
    \expect($view[2]['title'])->toBe('Zeta Item');
    \expect($view[2]['current'])->toBe(9);
    \expect($view[2]['previous'])->toBeNull();
});

\test('historyForUser returns sessions in descending counted_at order', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $older = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'counted_at' => Carbon::now()->subDays(3),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $older->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 10,
    ]);
    $newest = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'counted_at' => Carbon::now()->subDay(),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $newest->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 5,
    ]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $rows = $service->historyForUser(
        $user,
        $store,
        null,
        Carbon::now()->subDays(7),
        Carbon::now(),
        100,
    );

    \expect($rows)->toHaveCount(2);
    \expect($rows[0]['id'])->toBe($newest->getKey());
    \expect($rows[1]['id'])->toBe($older->getKey());
    \expect($rows[0]['item_count'])->toBe(1);
});

\test('historyForUser excludes sessions outside the date range', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $old = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'counted_at' => Carbon::now()->subDays(60),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $old->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 7,
    ]);
    $recent = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'counted_at' => Carbon::now()->subDays(2),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $recent->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 3,
    ]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $rows = $service->historyForUser(
        $user,
        $store,
        null,
        Carbon::now()->subDays(30),
        Carbon::now(),
        100,
    );

    \expect($rows)->toHaveCount(1);
    \expect($rows[0]['id'])->toBe($recent->getKey());
});

\test('historyForUser filters by item id when provided', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $a = Item::factory()->create(['user_id' => $user->getKey()]);
    $b = Item::factory()->create(['user_id' => $user->getKey()]);

    $sessionA = InventorySession::factory()->forStore($store)->byUser($user)->create();
    InventorySessionItem::factory()->create([
        'session_id' => $sessionA->getKey(),
        'item_id' => $a->getKey(),
    ]);
    $sessionB = InventorySession::factory()->forStore($store)->byUser($user)->create();
    InventorySessionItem::factory()->create([
        'session_id' => $sessionB->getKey(),
        'item_id' => $b->getKey(),
    ]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $rows = $service->historyForUser(
        $user,
        $store,
        $a,
        Carbon::now()->subDays(7),
        Carbon::now(),
        100,
    );

    \expect($rows)->toHaveCount(1);
    \expect($rows[0]['id'])->toBe($sessionA->getKey());
});

\test('buildSessionView returns rows in alphabetical order with previous quantity', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $zeta = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Zeta Item']);
    $alpha = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Alpha Item']);

    $previous = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'counted_at' => Carbon::now()->subDays(2),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $previous->getKey(),
        'item_id' => $alpha->getKey(),
        'quantity' => 6,
    ]);
    $current = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'counted_at' => Carbon::now(),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $current->getKey(),
        'item_id' => $alpha->getKey(),
        'quantity' => 8,
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $current->getKey(),
        'item_id' => $zeta->getKey(),
        'quantity' => 4,
    ]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $rows = $service->buildSessionView($user, $current);

    \expect($rows)->toHaveCount(2);
    \expect($rows[0]['title'])->toBe('Alpha Item');
    \expect($rows[0]['current'])->toBe(8);
    \expect($rows[0]['previous'])->toBe(6);
    \expect($rows[1]['title'])->toBe('Zeta Item');
    \expect($rows[1]['current'])->toBe(4);
    \expect($rows[1]['previous'])->toBeNull();
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

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
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

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
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

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
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

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $prediction = $service->predictedRunOut($retail, $item, 30);

    \expect($prediction['status'])->toBe('no_data');
    \expect($prediction['days_left'])->toBeNull();
});

\test('sparklineForItem densifies missing days and keeps the latest count per day', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $older = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'counted_at' => Carbon::now()->subDays(5),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $older->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 12,
    ]);
    $newer = InventorySession::factory()->forStore($store)->byUser($user)->create([
        'counted_at' => Carbon::now()->subDay(),
    ]);
    InventorySessionItem::factory()->create([
        'session_id' => $newer->getKey(),
        'item_id' => $item->getKey(),
        'quantity' => 7,
    ]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $sparkline = $service->sparklineForItem($user, $store, $item, 10);

    \expect($sparkline)->toHaveCount(10);
    \expect($sparkline[0]['value'])->toBeNull();
    $filled = \array_values(\array_filter(
        $sparkline,
        static fn(array $row): bool => $row['value'] !== null,
    ));
    \expect($filled)->toHaveCount(2);
});
