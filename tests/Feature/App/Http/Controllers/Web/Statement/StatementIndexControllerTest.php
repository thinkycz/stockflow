<?php

declare(strict_types=1);

use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected from statements to login', function (): void {
    $this->get('/statements')->assertRedirect('/login');
});

\test('authenticated user can view statements index', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    $response = $this->be($user, 'users')->get('/statements', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'statements/Index');
    $response->assertJsonPath('props.filters.store_id', $retail->getKey());
    $response->assertJsonCount(Carbon::now()->daysInMonth, 'props.days');
    $response->assertJsonPath('props.active_attendances', []);
    \expect($response->json('props.statement.id'))->toBeInt();
});

\test('statement is auto-created on first visit for the current month', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    \expect(Statement::query()->count())->toBe(0);

    $this->be($user, 'users')->get(
        '/statements?store_id=' . $retail->getKey(),
        $this->inertiaHeaders(),
    )->assertOk();

    \expect(Statement::query()->count())->toBe(1);
    \expect(StatementDay::query()->count())->toBe(Carbon::now()->daysInMonth);
});

\test('statement is reused on subsequent visits', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create([
        'user_id' => $user->getKey(),
        'is_warehouse' => false,
    ]);

    $this->be($user, 'users')->get(
        '/statements?store_id=' . $retail->getKey(),
        $this->inertiaHeaders(),
    )->assertOk();
    $this->be($user, 'users')->get(
        '/statements?store_id=' . $retail->getKey(),
        $this->inertiaHeaders(),
    )->assertOk();

    \expect(Statement::query()->count())->toBe(1);
});

\test('inactive store statements remain readable without creating new mutable periods', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->inactive()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 6)->create();
    $day = StatementDay::factory()->for($statement, 'statement')->create([
        'date' => '2026-06-01',
        'cash' => 25,
        'total' => 25,
    ]);

    $this->be($user, 'users')->get(
        '/statements?store_id=' . $store->getKey() . '&year=2026&month=6',
        $this->inertiaHeaders(),
    )->assertOk()
        ->assertJsonPath('props.store.id', $store->getKey())
        ->assertJsonPath('props.store.is_active', false)
        ->assertJsonPath('props.editable', false)
        ->assertJsonPath('props.statement.id', $statement->getKey())
        ->assertJsonPath('props.days.0.id', $day->getKey())
        ->assertJsonPath('props.today_statement', null)
        ->assertJsonPath('props.today_day', null)
        ->assertJsonPath('props.active_attendances', []);

    \expect(Statement::query()->where('store_id', $store->getKey())->count())->toBe(1);
});

\test('statements index respects requested month', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);

    $response = $this->be($user, 'users')->get(
        '/statements?store_id=' . $store->getKey() . '&year=2025&month=2',
        $this->inertiaHeaders(),
    );

    $response->assertOk();
    $response->assertJsonPath('props.filters.year', 2025);
    $response->assertJsonPath('props.filters.month', 2);
    \expect($response->json('props.days'))->toHaveCount(28);
});

\test('statements show a live monthly summary for a confirmed bank import', function (): void {
    [$user] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $user->getKey()]);
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 8)->create();
    StatementDay::factory()->for($statement, 'statement')->create([
        'date' => '2026-08-01',
        'card' => '1000.00',
        'total' => '1000.00',
    ]);
    $bankStatement = BankStatement::factory()->forStore($store)->create(['status' => 'confirmed']);
    BankStatementTransaction::factory()->forStatement($bankStatement)->create([
        'amount' => '990.00',
        'sales_from' => '2026-08-01',
        'sales_to' => '2026-08-01',
    ]);

    $this->be($user, 'users')->get(
        '/statements?store_id=' . $store->getKey() . '&year=2026&month=8',
        $this->inertiaHeaders(),
    )->assertOk()
        ->assertJsonPath('props.bank_reconciliation.statement_id', $bankStatement->getKey())
        ->assertJsonPath('props.bank_reconciliation.status', 'confirmed')
        ->assertJsonPath('props.bank_reconciliation.counts.matched', 1);

    StatementDay::query()->whereDate('date', '2026-08-01')->update(['card' => '900.00']);

    $this->be($user, 'users')->get(
        '/statements?store_id=' . $store->getKey() . '&year=2026&month=8',
        $this->inertiaHeaders(),
    )->assertJsonPath('props.bank_reconciliation.counts.mismatch', 1);
});

\test('statements expose todays row independently from the selected month', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    [$user, $store] = \createIsolatedUserWithWarehouse();

    $response = $this->be($user, 'users')->get(
        '/statements?store_id=' . $store->getKey() . '&year=2026&month=6',
        $this->inertiaHeaders(),
    );

    $response->assertOk()
        ->assertJsonPath('props.filters.month', 6)
        ->assertJsonPath('props.today_statement.store_id', $store->getKey())
        ->assertJsonPath('props.today_statement.year', 2026)
        ->assertJsonPath('props.today_statement.month', 7)
        ->assertJsonPath('props.today_day.date', '2026-07-23')
        ->assertJsonPath('props.today_day.total', 0);

    Carbon::setTestNow();
});

\test('statements expose entered values for todays panel visibility decision', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    [$user, $store] = \createIsolatedUserWithWarehouse();
    $statement = Statement::factory()->forStore($store)->forMonth(2026, 7)->create();
    StatementDay::factory()->for($statement, 'statement')->create([
        'date' => '2026-07-23',
        'cash' => 100,
        'total' => 100,
    ]);

    $this->be($user, 'users')
        ->get('/statements?store_id=' . $store->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('props.today_day.cash', 100)
        ->assertJsonPath('props.today_day.total', 100);

    Carbon::setTestNow();
});

\test('statements index is isolated per user', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    [$other] = \createIsolatedUserWithWarehouse();
    $foreignStore = Store::factory()->create(['user_id' => $other->getKey()]);

    $response = $this->be($user, 'users')->get(
        '/statements?store_id=' . $foreignStore->getKey(),
        $this->inertiaHeaders(),
    );

    $response->assertOk();
    \expect($response->json('props.filters.store_id'))->toBeNull();
});

\test('limited user is pinned to their assigned store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $admin->update(['is_admin' => true, 'parent_user_id' => null, 'assigned_store_id' => null]);

    $own = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $other = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($own)->createOne(), User::class);

    $response = $this->be($limited, 'users')->get('/statements', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('props.filters.store_id', $own->getKey());
    $response->assertJsonPath('props.is_admin', false);
    $response->assertJsonPath('props.active_attendances', []);

    // A `?store_id=` override for a non-assigned store is silently
    // ignored — the resolver always pins limited users to their
    // assigned store.
    $overrideResponse = $this->be($limited, 'users')
        ->get('/statements?store_id=' . $other->getKey(), $this->inertiaHeaders());
    $overrideResponse->assertOk();
    $overrideResponse->assertJsonPath('props.filters.store_id', $own->getKey());
});

\test('limited users and admins see all active current-day attendance employees ordered by name', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-23 10:00:00', 'Europe/Prague'));
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    $zoe = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Zoe',
        'last_name' => 'Adams',
    ]);
    $alice = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Alice',
        'last_name' => 'Brown',
    ]);
    $stale = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Stale',
        'last_name' => 'Worker',
    ]);

    foreach ([$alice, $zoe] as $worker) {
        AttendanceSession::query()->create([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
            'worker_id' => $worker->getKey(),
            'created_by_user_id' => $limited->getKey(),
            'active_worker_id' => $worker->getKey(),
            'hourly_rate' => $worker->getHourlyRate(),
            'started_at' => Carbon::parse('2026-07-23 08:00:00', 'Europe/Prague')->utc(),
        ]);
    }
    $aliceSession = AttendanceSession::query()->where('worker_id', $alice->getKey())->firstOrFail();
    AttendanceBreak::query()->create([
        'attendance_session_id' => $aliceSession->getKey(),
        'created_by_user_id' => $limited->getKey(),
        'active_session_id' => null,
        'started_at' => Carbon::parse('2026-07-23 09:00:00', 'Europe/Prague')->utc(),
        'ended_at' => Carbon::parse('2026-07-23 09:15:00', 'Europe/Prague')->utc(),
    ]);
    AttendanceSession::query()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $stale->getKey(),
        'created_by_user_id' => $limited->getKey(),
        'active_worker_id' => $stale->getKey(),
        'hourly_rate' => $stale->getHourlyRate(),
        'started_at' => Carbon::parse('2026-07-22 08:00:00', 'Europe/Prague')->utc(),
    ]);

    $response = $this->be($limited, 'users')->get('/statements', $this->inertiaHeaders());

    $response->assertOk()
        ->assertJsonCount(2, 'props.active_attendances')
        ->assertJsonPath('props.active_attendances.0.worker_id', $zoe->getKey())
        ->assertJsonPath('props.active_attendances.0.worker_name', 'Zoe Adams')
        ->assertJsonPath('props.active_attendances.0.worked_seconds', 7200)
        ->assertJsonPath('props.active_attendances.0.is_on_break', false)
        ->assertJsonPath('props.active_attendances.1.worker_id', $alice->getKey())
        ->assertJsonPath('props.active_attendances.1.worker_name', 'Alice Brown')
        ->assertJsonPath('props.active_attendances.1.worked_seconds', 6300)
        ->assertJsonPath('props.active_attendances.1.is_on_break', false);

    $this->be($admin, 'users')
        ->get('/statements?store_id=' . $store->getKey(), $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'props.active_attendances')
        ->assertJsonPath('props.active_attendances.0.worker_id', $zoe->getKey())
        ->assertJsonPath('props.active_attendances.0.worker_name', 'Zoe Adams')
        ->assertJsonPath('props.active_attendances.1.worker_id', $alice->getKey())
        ->assertJsonPath('props.active_attendances.1.worker_name', 'Alice Brown');

    Carbon::setTestNow();
});

\test('limited user without an assigned store is refused', function (): void {
    $limited = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $this->be($limited, 'users')
        ->get('/statements', $this->inertiaHeaders())
        ->assertForbidden();
});
