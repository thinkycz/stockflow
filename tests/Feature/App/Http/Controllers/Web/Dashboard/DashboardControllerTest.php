<?php

declare(strict_types=1);

use App\Enums\LimitedUserSectionEnum;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Item;
use App\Models\NoticeboardCard;
use App\Models\Shift;
use App\Models\StockMovement;
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
                'low_stock_items',
                'today_movements',
                'last_inventory_at',
            ],
            'recent_movements',
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
    \expect($response->json('props.recent_movements'))->toBe([]);
    \expect($response->json('props'))->not->toHaveKeys(['stock_status', 'top_consumed', 'recent_statements']);
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

\test('dashboard reports visible low stock metric for the active store', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();

    $itemInStock = Item::factory()->create(['user_id' => $user->getKey()]);
    $itemLowStock = Item::factory()->create(['user_id' => $user->getKey()]);
    $itemOutOfStock = Item::factory()->create(['user_id' => $user->getKey()]);

    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemInStock->getKey(), 'quantity' => 20]);
    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemLowStock->getKey(), 'quantity' => 3]);
    StoreItem::factory()->create(['store_id' => $warehouse->getKey(), 'item_id' => $itemOutOfStock->getKey(), 'quantity' => 0]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

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

\test('dashboard exposes the outgoing display label for warehouse dispatches', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    StockMovement::factory()->transfer($retail)->create([
        'user_id' => $user->getKey(),
        'source_store_id' => $warehouse->getKey(),
    ]);

    $response = $this->be($user, 'users')
        ->get('/dashboard?store_id=' . $warehouse->getKey(), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.recent_movements.0.display_label_key', 'outgoing');
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
    \expect($response->json('props.recent_movements'))->toBe([]);
    \expect($response->json('props'))->not->toHaveKeys(['stock_status', 'top_consumed', 'recent_statements']);
    \expect($response->json('props.is_admin'))->toBeFalse();
});

\test('retail dashboard exposes both daily checklist cards and workers', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 09:00:00', 'Europe/Prague'));
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $this->withSession(\activeStoreSession($store));
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'first_name' => 'Anna', 'last_name' => 'Nováková']);

    $response = $this->be($admin, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.checklists.shifts.morning.items'))->toHaveCount(10)
        ->and($response->json('props.checklists.shifts.afternoon.items'))->toHaveCount(12)
        ->and($response->json('props.checklists.workers.0.id'))->toBe($worker->getKey());
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
    \expect($response->json('props.operations.shifts.current_shifts'))->toHaveCount(1);
    $response->assertJsonPath('props.operations.shifts.current_shifts.0.worker_name', 'Anna Nováková');
    $response->assertJsonPath('props.operations.shifts.current_shifts.0.start_time', '08:00');
    $response->assertJsonPath('props.operations.shifts.current_shifts.0.end_time', '12:00');
    $response->assertJsonPath('props.operations.shifts.next_shift.worker_name', 'Boris Malý');
    $response->assertJsonPath('props.operations.shifts.next_shift.date', '2026-07-22');
    $response->assertJsonPath('props.operations.shifts.next_shift.start_time', '14:00');
    $response->assertJsonPath('props.operations.shifts.next_shift.end_time', '18:00');
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
    \expect($response->json('props.operations.shifts.current_shifts'))->toBe([]);
    $response->assertJsonPath('props.operations.attendance.workers.0.worker_name', 'Anna Nováková');

    CarbonImmutable::setTestNow();
});

\test('limited dashboard omits disabled shifts attendance and checklists', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = UserFactory::new()->limited($store)->createOne([
        'disabled_sections' => [
            LimitedUserSectionEnum::SHIFTS->value,
            LimitedUserSectionEnum::ATTENDANCE->value,
            LimitedUserSectionEnum::CHECKLISTS->value,
        ],
    ]);

    $response = $this->be($limited, 'users')->get('/dashboard', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.operations.shifts', null);
    $response->assertJsonPath('props.operations.attendance', null);
    $response->assertJsonPath('props.checklists', null);
});

\test('dashboard noticeboard filters active expired searched and trashed cards for the active store', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    $this->withSession(\activeStoreSession($store));
    $active = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
        'title' => 'Hledaný aktivní záznam',
        'body_text' => 'porada',
        'label' => 'important',
        'size' => 'large',
        'expires_at' => null,
    ]);
    $expired = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
        'title' => 'Starý záznam',
        'expires_at' => CarbonImmutable::now()->subDay(),
    ]);
    NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $otherStore->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
        'title' => 'Cizí záznam',
    ]);
    $trashed = NoticeboardCard::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
    ]);
    $trashed->delete();

    $activeResponse = $this->be($admin, 'users')->get('/dashboard?search=porada&label=important', $this->inertiaHeaders());
    $activeResponse->assertOk();
    \expect($activeResponse->json('props.noticeboard.cards'))->toHaveCount(1)
        ->and($activeResponse->json('props.noticeboard.cards.0.id'))->toBe($active->getKey())
        ->and($activeResponse->json('props.noticeboard.cards.0.size'))->toBe('large');

    $expiredResponse = $this->be($admin, 'users')->get('/dashboard?status=expired', $this->inertiaHeaders());
    \expect(\array_column($expiredResponse->json('props.noticeboard.cards'), 'id'))->toBe([$expired->getKey()]);

    $trashResponse = $this->be($admin, 'users')->get('/dashboard?status=trash', $this->inertiaHeaders());
    \expect(\array_column($trashResponse->json('props.noticeboard.cards'), 'id'))->toBe([$trashed->getKey()]);
});

\test('limited user cannot open the noticeboard trash filter', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $limited = UserFactory::new()->limited($store)->createOne();

    $this->be($limited, 'users')->get('/dashboard?status=trash', $this->inertiaHeaders())->assertForbidden();
});

\test('dashboard noticeboard paginates twenty four newest cards at a time', function (): void {
    [$admin, $store] = \createIsolatedUserWithWarehouse();
    $this->withSession(\activeStoreSession($store));
    NoticeboardCard::factory()->count(25)->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'updated_by_user_id' => $admin->getKey(),
    ]);

    $first = $this->be($admin, 'users')->get('/dashboard', $this->inertiaHeaders());
    $second = $this->be($admin, 'users')->get('/dashboard?page=2', $this->inertiaHeaders());

    \expect($first->json('props.noticeboard.cards'))->toHaveCount(24)
        ->and($first->json('props.noticeboard.pagination.total'))->toBe(25)
        ->and($first->json('props.noticeboard.pagination.per_page'))->toBe(24)
        ->and($second->json('props.noticeboard.cards'))->toHaveCount(1);
});
