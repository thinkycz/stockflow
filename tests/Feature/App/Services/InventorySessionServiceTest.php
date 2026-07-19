<?php

declare(strict_types=1);

use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use App\Services\InventorySessionService;
use App\Services\StockMovementService;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-24 10:00:00'));
});

\test('inventory differences create one immutable reconciliation with signed classifications', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $decreased = Item::factory()->create(['user_id' => $user->getKey()]);
    $increased = Item::factory()->create(['user_id' => $user->getKey()]);
    $unchanged = Item::factory()->create(['user_id' => $user->getKey()]);
    foreach ([$decreased, $increased, $unchanged] as $item) {
        StoreItem::factory()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 10]);
    }

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $session = $service->createSession($user, $store, [
        ['item_id' => $decreased->getKey(), 'quantity' => 7],
        ['item_id' => $increased->getKey(), 'quantity' => 12],
        ['item_id' => $unchanged->getKey(), 'quantity' => 10],
    ]);

    $movement = StockMovement::query()->where('inventory_session_id', $session->getKey())->firstOrFail();
    $rows = StockMovementItem::query()->where('stock_movement_id', $movement->getKey())->orderBy('item_id')->get();

    \expect($movement->getType()->value)->toBe('inventory_reconciliation');
    \expect($rows)->toHaveCount(2);
    \expect($rows->firstWhere('item_id', $decreased->getKey())?->getQuantityDifference())->toBe(-3);
    \expect($rows->firstWhere('item_id', $decreased->getKey())?->getClassification()?->value)->toBe('consumption');
    \expect($rows->firstWhere('item_id', $increased->getKey())?->getQuantityDifference())->toBe(2);
    \expect($rows->firstWhere('item_id', $increased->getKey())?->getClassification()?->value)->toBe('inventory_correction');
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

\test('closed inventory intervals count consumption but never transfers', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $movements = Typer::assertInstance(\app(StockMovementService::class), StockMovementService::class);
    Carbon::setTestNow('2026-06-10 10:00:00');
    $service->createSession($user, $retail, [['item_id' => $item->getKey(), 'quantity' => 20]]);

    Carbon::setTestNow('2026-06-17 10:00:00');
    $movements->createMovement([
        'mode' => 'transfer',
        'source_store_id' => $retail->getKey(),
        'store_id' => $warehouse->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 3]],
    ], $user);
    $movements->createMovement([
        'mode' => 'consumption',
        'store_id' => $retail->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 2]],
    ], $user);

    Carbon::setTestNow('2026-06-24 10:00:00');
    $service->createSession($user, $retail, [['item_id' => $item->getKey(), 'quantity' => 14]]);
    $consumption = $service->consumptionLastDays($retail, $item, 30);

    // 2 manual consumption + 1 unexplained inventory decrease. The transfer of 3 is excluded.
    \expect($consumption['quantity'])->toBe(3);
    \expect((float) $consumption['coverage_days'])->toBe(14.0);
    \expect($consumption['per_day'])->toBe(3 / 14);
});

\test('predictedRunOut returns days_left based on current quantity and consumption', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);
    $item = Item::factory()->create(['user_id' => $user->getKey()]);

    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    Carbon::setTestNow('2026-06-10 10:00:00');
    $service->createSession($user, $retail, [['item_id' => $item->getKey(), 'quantity' => 30]]);
    Carbon::setTestNow('2026-06-24 10:00:00');
    $service->createSession($user, $retail, [['item_id' => $item->getKey(), 'quantity' => 16]]);
    $prediction = $service->predictedRunOut($retail, $item, 30);

    \expect($prediction['current'])->toBe(16);
    \expect($prediction['status'])->toBe('ok');
    \expect((float) $prediction['per_day'])->toBe(1.0);
    \expect($prediction['days_left'])->toBe(16);
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

\test('draft reconciliation preserves movements posted after a row was counted', function (): void {
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create(['store_id' => $store->getKey(), 'item_id' => $item->getKey(), 'quantity' => 10]);
    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $draft = $service->startDraft($user, $store);
    $service->saveDraftRow($user, $draft, [
        'item_id' => $item->getKey(), 'quantity' => 7, 'classification' => 'consumption', 'client_version' => 1,
    ]);

    Typer::assertInstance(\app(StockMovementService::class), StockMovementService::class)->createMovement([
        'mode' => 'incoming', 'store_id' => $store->getKey(),
        'items' => [['item_id' => $item->getKey(), 'quantity' => 5]],
    ], $user);
    $service->closeDraft($user, $draft);

    \expect((int) StoreItem::query()->where('store_id', $store->getKey())->where('item_id', $item->getKey())->value('quantity'))->toBe(12)
        ->and($draft->fresh()?->getStatus())->toBe('closed');
});

\test('draft is unique per store and stale autosave cannot overwrite a newer row', function (): void {
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $item = Item::factory()->create(['user_id' => $user->getKey()]);
    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $draft = $service->startDraft($user, $store);

    \expect($service->startDraft($user, $store)->getKey())->toBe($draft->getKey());
    $service->saveDraftRow($user, $draft, ['item_id' => $item->getKey(), 'quantity' => 8, 'client_version' => 2]);
    $row = $service->saveDraftRow($user, $draft, ['item_id' => $item->getKey(), 'quantity' => 3, 'client_version' => 1]);

    \expect($row->getQuantity())->toBe(8)
        ->and(InventorySession::query()->where('active_store_key', $store->getKey())->count())->toBe(1);
});

\test('closing a partial draft leaves uncounted items untouched', function (): void {
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $counted = Item::factory()->create(['user_id' => $user->getKey()]);
    $untouched = Item::factory()->create(['user_id' => $user->getKey()]);
    StoreItem::query()->create(['store_id' => $store->getKey(), 'item_id' => $counted->getKey(), 'quantity' => 10]);
    StoreItem::query()->create(['store_id' => $store->getKey(), 'item_id' => $untouched->getKey(), 'quantity' => 9]);
    $service = Typer::assertInstance(\app(InventorySessionService::class), InventorySessionService::class);
    $draft = $service->startDraft($user, $store);
    $service->saveDraftRow($user, $draft, ['item_id' => $counted->getKey(), 'quantity' => 7, 'client_version' => 1]);
    $service->closeDraft($user, $draft);

    \expect((int) StoreItem::query()->where('store_id', $store->getKey())->where('item_id', $untouched->getKey())->value('quantity'))->toBe(9);
});
