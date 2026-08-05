<?php

declare(strict_types=1);

use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;

\test('admin sees shifts for the active store in the calendar', function (): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Anna',
        'last_name' => 'Adams',
        'hourly_rate' => 200.50,
        'calendar_color' => '#12ABEF',
    ]);
    $workerWithoutShifts = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Zdenek',
        'last_name' => 'Zeman',
        'hourly_rate' => 180,
    ]);
    ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'name' => 'Evening',
        'start_time' => '15:00',
        'end_time' => '21:00',
    ]);
    ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'name' => 'Morning',
        'start_time' => '10:00',
        'end_time' => '15:00',
    ]);

    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
        'start_time' => '10:00',
        'end_time' => '15:00',
        'hourly_rate' => 200.50,
    ]);

    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $workerWithoutShifts->getKey(),
        'date' => '2026-08-01',
        'start_time' => '09:00',
        'end_time' => '12:00',
        'hourly_rate' => 180,
    ]);

    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-16',
        'start_time' => '15:30',
        'end_time' => '19:00',
        'hourly_rate' => 200.50,
    ]);

    $worker->update(['hourly_rate' => 999]);

    $response = $this->be($admin, 'users')->get(\route('shifts.index', ['year' => 2026, 'month' => 7]), $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'shifts/Index');
    $response->assertJsonPath('props.shifts.0.worker_id', $worker->getKey());
    $response->assertJsonPath('props.shifts.0.date', '2026-07-15');
    $response->assertJsonPath('props.shifts.0.start_time', '10:00');
    $response->assertJsonPath('props.shifts.0.end_time', '15:00');
    $response->assertJsonPath('props.is_admin', true);
    $response->assertJsonPath('props.monthly_summary.0.worker_id', $worker->getKey());
    $response->assertJsonPath('props.monthly_summary.0.worker_name', 'Anna Adams');
    $response->assertJsonPath('props.workers.0.color', '#12ABEF');
    $response->assertJsonPath('props.monthly_summary.0.color', '#12ABEF');
    $response->assertJsonPath('props.monthly_summary.0.hours', 8.5);
    $response->assertJsonPath('props.monthly_summary.0.salary', 1704.25);
    $response->assertJsonPath('props.monthly_summary.0.absences', 2);
    \expect($response->json('props.monthly_summary'))->toHaveCount(1);
    $response->assertJsonMissingPath('props.monthly_summary.1');
    $response->assertJsonMissingPath('props.worker_summary');
    $response->assertJsonMissingPath('props.attendance_summary');
    $response->assertJsonPath('props.shift_presets.0.name', 'Morning');
    $response->assertJsonPath('props.shift_presets.0.start_time', '10:00');
    $response->assertJsonPath('props.shift_presets.1.name', 'Evening');
    \expect($response->json('props.workers'))->toHaveCount(2);
});

\test('admin does not see shifts from another store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $storeA = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $storeB = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $storeB->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
    ]);

    $response = $this->be($admin, 'users')->get(\route('shifts.index', ['store_id' => $storeA->getKey(), 'year' => 2026, 'month' => 7]), $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.shifts'))->toHaveCount(0);
});

\test('limited user sees shifts with worker names and is_admin is false', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();

    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
    ]);

    $response = $this->be($limited, 'users')->get(\route('shifts.index', ['year' => 2026, 'month' => 7]), $this->inertiaHeaders());

    $response->assertOk();
    \expect($response->json('props.shifts'))->toHaveCount(1);
    $response->assertJsonPath('props.workers.0.id', $worker->getKey());
    $response->assertJsonPath('props.workers.0.first_name', $worker->getFirstName());
    $response->assertJsonPath('props.workers.0.last_name', $worker->getLastName());
    $response->assertJsonPath('props.workers.0.color', $worker->getCalendarColor());
    $response->assertJsonMissingPath('props.workers.0.hourly_rate');
    $response->assertJsonPath('props.monthly_summary.0.worker_id', $worker->getKey());
    $response->assertJsonPath('props.monthly_summary.0.hours', 4);
    $response->assertJsonMissingPath('props.monthly_summary.0.salary');
    $response->assertJsonMissingPath('props.worker_summary');
    $response->assertJsonMissingPath('props.attendance_summary');
    $response->assertJsonMissingPath('props.shift_presets');
    $response->assertJsonPath('props.is_admin', false);
});

\test('limited user sees attendance ratings without financial summary', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(), 'first_name' => 'Anna', 'last_name' => 'Adams',
    ]);
    $limited = UserFactory::new()->limited($store)->createOne();
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-15', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'scheduled_date' => '2026-07-15',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-15 06:00:00', 'ended_at' => '2026-07-15 14:00:00',
    ]);
    Carbon::setTestNow('2026-07-31 10:00:00 UTC');

    $response = $this->be($limited, 'users')->get(
        \route('shifts.index', ['year' => 2026, 'month' => 7]),
        $this->inertiaHeaders(),
    );

    $response->assertOk();
    $response->assertJsonPath('props.shifts.0.attendance_rating.score', 100);
    $response->assertJsonPath('props.shifts.0.attendance_rating.band', 'good');
    $response->assertJsonPath('props.monthly_summary.0.worker_name', 'Anna Adams');
    $response->assertJsonPath('props.monthly_summary.0.hours', 8);
    $response->assertJsonPath('props.monthly_summary.0.average_score', 100);
    $response->assertJsonPath('props.monthly_summary.0.good_shifts', 1);
    $response->assertJsonMissingPath('props.monthly_summary.0.salary');
    $response->assertJsonMissingPath('props.worker_summary');
    $response->assertJsonMissingPath('props.attendance_summary');

    Carbon::setTestNow();
});

\test('admin keeps hours and salary while disabled attendance rating is omitted', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'hourly_rate' => 200,
        'attendance_rating_enabled' => false,
    ]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-15', 'start_time' => '08:00', 'end_time' => '16:00', 'hourly_rate' => 200,
    ]);
    Carbon::setTestNow('2026-07-31 10:00:00 UTC');

    $response = $this->be($admin, 'users')->get(
        \route('shifts.index', ['store_id' => $store->getKey(), 'year' => 2026, 'month' => 7]),
        $this->inertiaHeaders(),
    );

    $response->assertOk()
        ->assertJsonPath('props.workers.0.attendance_rating_enabled', false)
        ->assertJsonPath('props.shifts.0.attendance_rating.state', 'disabled')
        ->assertJsonPath('props.shifts.0.attendance_rating.score', null)
        ->assertJsonPath('props.shifts.0.attendance_rating.break_minutes', null)
        ->assertJsonPath('props.monthly_summary.0.attendance_rating_enabled', false)
        ->assertJsonPath('props.monthly_summary.0.average_score', null)
        ->assertJsonPath('props.monthly_summary.0.good_shifts', null)
        ->assertJsonPath('props.monthly_summary.0.absences', null)
        ->assertJsonPath('props.monthly_summary.0.hours', 8)
        ->assertJsonPath('props.monthly_summary.0.salary', 1600);

    Carbon::setTestNow();
});

\test('guest is redirected to the login screen', function (): void {
    $this->get(\route('shifts.index'), $this->inertiaHeaders())->assertRedirect('/login');
});
