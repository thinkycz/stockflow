<?php

declare(strict_types=1);

use App\Enums\StockMovementClassificationEnum;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Item;
use App\Models\Shift;
use App\Models\Statement;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Models\Worker;
use App\Services\InventorySessionService;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected from dashboard to login', function (): void {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

\test('authenticated user can view dashboard with metrics', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $response = $this->be($user, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'Dashboard');
    $response->assertJsonPath('props.auth.user.email', $user->getEmail());
    $response->assertJsonPath('props.is_admin', true);
    $response->assertJsonStructure([
        'props' => [
            'active_store',
            'metrics' => [
                'inventory_value',
                'items_count',
                'low_stock_items',
                'today_movements',
                'month_incoming',
                'month_outgoing',
            ],
            'stock_status' => ['in_stock', 'low_stock', 'out_of_stock', 'no_data'],
            'top_consumed',
            'recent_movements',
            'recent_statements',
        ],
    ]);
});

\test('dashboard returns empty payload without an active store', function (): void {
    $user = UserFactory::new()->admin()->createOne();

    $response = $this->be($user, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.active_store'))->toBeNull();
    \expect($response->json('props.is_admin'))->toBeTrue();
    \expect((float) $response->json('props.metrics.inventory_value'))->toBe(0.0);
    \expect($response->json('props.metrics.items_count'))->toBe(0);
    \expect($response->json('props.stock_status.in_stock'))->toBe(0);
    \expect($response->json('props.top_consumed'))->toBe([]);
    \expect($response->json('props.recent_movements'))->toBe([]);
    \expect($response->json('props.recent_statements'))->toBe([]);
});

\test('dashboard ignores an open inventory draft without a finalized date', function (): void {
    [$user, $store] = \createIsolatedUserWithWarehouse();
    \app(InventorySessionService::class)->startDraft($user, $store);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $store->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.metrics.last_inventory_at'))->toBeNull();
});

\test('dashboard scopes inventory value to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $itemLocal = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '10.00']);
    $itemOther = Item::factory()->create(['user_id' => $user->getKey(), 'purchase_price' => '99.00']);

    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemLocal->getKey(), 'quantity' => 5]);
    StoreItem::factory()->create(['store_id' => $other->getKey(), 'item_id' => $itemOther->getKey(), 'quantity' => 100]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    \expect((float) $response->json('props.metrics.inventory_value'))->toBe(50.0);
    \expect($response->json('props.active_store.id'))->toBe($warehouse->getKey());
});

\test('dashboard classifies stock status for the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();

    $itemInStock = Item::factory()->create(['user_id' => $user->getKey()]);
    $itemLowStock = Item::factory()->create(['user_id' => $user->getKey()]);
    $itemOutOfStock = Item::factory()->create(['user_id' => $user->getKey()]);

    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemInStock->getKey(), 'quantity' => 20]);
    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemLowStock->getKey(), 'quantity' => 3]);
    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemOutOfStock->getKey(), 'quantity' => 0]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    \expect($response->json('props.stock_status.in_stock'))->toBe(0);
    \expect($response->json('props.stock_status.low_stock'))->toBe(0);
    \expect($response->json('props.stock_status.out_of_stock'))->toBe(1);
    \expect($response->json('props.stock_status.no_data'))->toBe(2);
    \expect($response->json('props.metrics.items_count'))->toBe(3);
    \expect($response->json('props.metrics.low_stock_items'))->toBe(0);
});

\test('dashboard scopes recent movements to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $local = StockMovement::factory()
        ->incoming()
        ->byUser($user)
        ->create(['user_id' => $user->getKey(), 'store_id' => $warehouse->getKey()]);
    $foreign = StockMovement::factory()
        ->incoming()
        ->byUser($user)
        ->create(['user_id' => $user->getKey(), 'store_id' => $other->getKey()]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    $ids = \array_column($response->json('props.recent_movements'), 'id');
    \expect($ids)->toContain($local->getKey());
    \expect($ids)->not->toContain($foreign->getKey());
});

\test('dashboard aggregates top consumed items for the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $itemLocal = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Local top']);
    $itemOther = Item::factory()->create(['user_id' => $user->getKey(), 'title' => 'Other-store top']);

    $localMovement = StockMovement::factory()
        ->consumption($warehouse)
        ->byUser($user)
        ->create(['user_id' => $user->getKey()]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $localMovement->getKey(),
        'item_id' => $itemLocal->getKey(),
        'quantity' => 4,
        'quantity_difference' => -4,
        'classification' => StockMovementClassificationEnum::CONSUMPTION->value,
        'total' => 40.0,
    ]);

    $otherMovement = StockMovement::factory()
        ->consumption($other)
        ->byUser($user)
        ->create(['user_id' => $user->getKey()]);
    StockMovementItem::factory()->create([
        'stock_movement_id' => $otherMovement->getKey(),
        'item_id' => $itemOther->getKey(),
        'quantity' => 99,
        'quantity_difference' => -99,
        'classification' => StockMovementClassificationEnum::CONSUMPTION->value,
        'total' => 9999.0,
    ]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    $topConsumed = $response->json('props.top_consumed');
    \expect($topConsumed)->toHaveCount(1);
    \expect($topConsumed[0]['item_id'])->toBe($itemLocal->getKey());
    \expect((float) $topConsumed[0]['total_quantity'])->toBe(4.0);
});

\test('limited dashboard exposes actions without statistics', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $limited = UserFactory::new()->limited($warehouse)->createOne();
    $item = Item::factory()->create(['user_id' => $admin->getKey(), 'purchase_price' => '10.00']);
    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $item->getKey(), 'quantity' => 3]);

    $response = $this->be($limited, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.active_store.id'))->toBe($warehouse->getKey());
    \expect($response->json('props.metrics'))->toBeNull();
    \expect($response->json('props.stock_status'))->toBeNull();
    \expect($response->json('props.top_consumed'))->toBe([]);
    \expect($response->json('props.recent_movements'))->toBe([]);
    \expect($response->json('props.recent_statements'))->toBe([]);
    \expect($response->json('props.is_admin'))->toBeFalse();
});

\test('limited dashboard shows current operations for its assigned store', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-22 10:00:00', 'Europe/Prague'));

    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $currentWorker = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Anna', 'last_name' => 'Nováková']);
    $nextWorker = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Boris', 'last_name' => 'Malý']);
    $breakWorker = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Cyril', 'last_name' => 'Veselý']);

    Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $currentWorker->getKey(),
        'date' => '2026-07-22', 'start_time' => '08:00', 'end_time' => '12:00',
    ]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $nextWorker->getKey(),
        'date' => '2026-07-22', 'start_time' => '14:00', 'end_time' => '18:00',
    ]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $otherStore->getKey(), 'worker_id' => $breakWorker->getKey(),
        'date' => '2026-07-22', 'start_time' => '11:00', 'end_time' => '12:00',
    ]);

    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $currentWorker->getKey(),
        'created_by_user_id' => $limited->getKey(), 'active_worker_id' => $currentWorker->getKey(),
        'started_at' => CarbonImmutable::parse('2026-07-22 08:00:00', 'Europe/Prague')->utc(), 'ended_at' => null,
    ]);
    $breakSession = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $breakWorker->getKey(),
        'created_by_user_id' => $limited->getKey(), 'active_worker_id' => $breakWorker->getKey(),
        'started_at' => CarbonImmutable::parse('2026-07-22 09:00:00', 'Europe/Prague')->utc(), 'ended_at' => null,
    ]);
    AttendanceBreak::factory()->create([
        'attendance_session_id' => $breakSession->getKey(), 'active_session_id' => $breakSession->getKey(),
        'started_at' => CarbonImmutable::parse('2026-07-22 09:45:00', 'Europe/Prague')->utc(), 'ended_at' => null,
    ]);

    $response = $this->be($limited, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.operations.current_shifts'))->toHaveCount(1);
    $response->assertJsonPath('props.operations.current_shifts.0.worker_name', 'Anna Nováková');
    $response->assertJsonPath('props.operations.current_shifts.0.start_time', '08:00');
    $response->assertJsonPath('props.operations.current_shifts.0.end_time', '12:00');
    $response->assertJsonPath('props.operations.next_shift.worker_name', 'Boris Malý');
    $response->assertJsonPath('props.operations.next_shift.date', '2026-07-22');
    $response->assertJsonPath('props.operations.next_shift.start_time', '14:00');
    $response->assertJsonPath('props.operations.next_shift.end_time', '18:00');
    \expect($response->json('props.operations.attendance.workers'))->toHaveCount(2);
    $response->assertJsonPath('props.operations.attendance.workers.0.worker_name', 'Anna Nováková');
    $response->assertJsonPath('props.operations.attendance.workers.0.status', 'present');
    $response->assertJsonPath('props.operations.attendance.workers.1.worker_name', 'Cyril Veselý');
    $response->assertJsonPath('props.operations.attendance.workers.1.status', 'break');
    \expect($response->json('props.operations.attendance.stale_count'))->toBe(0);

    CarbonImmutable::setTestNow();
});

\test('limited dashboard handles attendance when no shift is currently running', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-22 17:00:00', 'Europe/Prague'));

    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $limited = UserFactory::new()->limited($store)->createOne();
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(), 'first_name' => 'Anna', 'last_name' => 'Nováková',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'created_by_user_id' => $limited->getKey(), 'active_worker_id' => $worker->getKey(),
        'started_at' => CarbonImmutable::parse('2026-07-22 16:00:00', 'Europe/Prague')->utc(), 'ended_at' => null,
    ]);

    $response = $this->be($limited, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.operations.current_shifts'))->toBe([]);
    $response->assertJsonPath('props.operations.attendance.workers.0.worker_name', 'Anna Nováková');

    CarbonImmutable::setTestNow();
});

\test('dashboard scopes recent statements to the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $other = Store::factory()->create(['user_id' => $user->getKey()]);

    $localStatement = Statement::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $warehouse->getKey(),
        'year' => 2026,
        'month' => 5,
    ]);
    Statement::factory()->create([
        'user_id' => $user->getKey(),
        'store_id' => $other->getKey(),
        'year' => 2026,
        'month' => 5,
    ]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    $ids = \array_column($response->json('props.recent_statements'), 'id');
    \expect($ids)->toContain($localStatement->getKey());
    \expect(\count($ids))->toBe(1);
});
