<?php

declare(strict_types=1);

use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\ShiftShareLink;
use App\Models\Store;
use App\Models\Worker;
use Illuminate\Support\Carbon;

\test('public shift calendar renders its employee install metadata', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'token' => 'employee-install-token',
    ]);

    $this->get('/public/shifts/employee-install-token')
        ->assertOk()
        ->assertSee('content="Teacha Shifts"', false)
        ->assertSee('href="/public/shifts/employee-install-token/manifest.webmanifest"', false)
        ->assertDontSee('href="/manifest.webmanifest"', false);
});

\test('guest can view the shared store shift calendar with worker names', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'token' => 'shared-calendar-token',
    ]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Jan',
        'last_name' => 'Novak',
        'calendar_color' => '#12ABEF',
    ]);

    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
        'start_time' => '09:00',
        'end_time' => '16:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(),
        'scheduled_date' => '2026-07-15',
        'scheduled_start_time' => '09:00',
        'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-15 07:00:00',
        'ended_at' => '2026-07-15 14:00:00',
    ]);
    Carbon::setTestNow('2026-07-31 10:00:00 UTC');

    $response = $this->get('/public/shifts/shared-calendar-token?year=2026&month=7', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'public-shifts/Index');
    $response->assertJsonPath('props.store.name', $store->getName());
    $response->assertJsonPath('props.shifts.0.worker_name', 'Jan Novak');
    $response->assertJsonPath('props.shifts.0.worker_color', '#12ABEF');
    $response->assertJsonPath('props.shifts.0.date', '2026-07-15');
    $response->assertJsonPath('props.shifts.0.start_time', '09:00');
    $response->assertJsonPath('props.shifts.0.end_time', '16:00');
    $response->assertJsonPath('props.shifts.0.attendance_rating.state', 'scored');
    $response->assertJsonPath('props.shifts.0.attendance_rating.score', 100);
    $response->assertJsonPath('props.shifts.0.attendance_rating.band', 'good');
    $response->assertJsonMissingPath('props.shifts.0.attendance_rating.reason_codes');
    $response->assertJsonMissingPath('props.shifts.0.attendance_rating.late_minutes');
    $response->assertJsonPath('props.monthly_summary.0.worker_name', 'Jan Novak');
    $response->assertJsonPath('props.monthly_summary.0.color', '#12ABEF');
    $response->assertJsonPath('props.monthly_summary.0.hours', 7);
    $response->assertJsonPath('props.monthly_summary.0.average_score', 100);
    $response->assertJsonPath('props.monthly_summary.0.good_shifts', 1);
    $response->assertJsonMissingPath('props.monthly_summary.0.salary');

    Carbon::setTestNow();
});

\test('shared calendar excludes shifts from another store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $sharedStore = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $sharedStore->getKey(),
        'token' => 'shared-calendar-token',
    ]);
    $otherStore = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);

    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $sharedStore->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-15',
    ]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $otherStore->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-16',
    ]);

    $response = $this->get('/public/shifts/shared-calendar-token?year=2026&month=7', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonCount(1, 'props.shifts');
    $response->assertJsonPath('props.shifts.0.date', '2026-07-15');
    $response->assertJsonCount(1, 'props.monthly_summary');
    $response->assertJsonPath('props.monthly_summary.0.worker_id', $worker->getKey());
});

\test('shared calendar does not publish disabled attendance rating metrics', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]);
    ShiftShareLink::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'token' => 'disabled-rating-token',
    ]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'attendance_rating_enabled' => false,
    ]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-15', 'start_time' => '09:00', 'end_time' => '16:00',
    ]);
    Carbon::setTestNow('2026-07-31 10:00:00 UTC');

    $response = $this->get('/public/shifts/disabled-rating-token?year=2026&month=7', $this->inertiaHeaders());

    $response->assertOk()
        ->assertJsonPath('props.shifts.0.attendance_rating.state', 'disabled')
        ->assertJsonPath('props.shifts.0.attendance_rating.score', null)
        ->assertJsonPath('props.shifts.0.attendance_rating.band', null)
        ->assertJsonPath('props.monthly_summary.0.attendance_rating_enabled', false)
        ->assertJsonPath('props.monthly_summary.0.average_score', null)
        ->assertJsonPath('props.monthly_summary.0.late_arrivals', null)
        ->assertJsonPath('props.monthly_summary.0.hours', 7)
        ->assertJsonMissingPath('props.monthly_summary.0.salary');

    Carbon::setTestNow();
});

\test('unknown public shift calendar token returns not found', function (): void {
    $this->get('/public/shifts/unknown-token', $this->inertiaHeaders())->assertNotFound();
});
