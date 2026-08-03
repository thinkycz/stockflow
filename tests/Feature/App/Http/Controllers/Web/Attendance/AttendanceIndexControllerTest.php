<?php

declare(strict_types=1);

use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;

\afterEach(function (): void {
    Carbon::setTestNow();
});

\test('admin and assigned limited user can open attendance for the retail store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($store)->createOne();

    $this->be($admin, 'users')->get('/attendance', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('component', 'attendance/Index')
        ->assertJsonPath('props.off_schedule_workers.0.id', $worker->getKey())
        ->assertJsonPath('props.is_admin', true)
        ->assertJsonMissingPath('props.report');

    $this->be($limited, 'users')->get('/attendance', $this->inertiaHeaders())
        ->assertOk()->assertJsonPath('props.store.id', $store->getKey())
        ->assertJsonPath('props.is_admin', false)->assertJsonMissingPath('props.report');
});

\test('attendance rows combine today shifts and active workers with monthly quality', function (): void {
    Carbon::setTestNow('2026-08-15 10:00:00 UTC');
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $scheduled = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Anna',
        'last_name' => 'Scheduled',
        'attendance_rating_enabled' => false,
    ]);
    $active = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Boris',
        'last_name' => 'Active',
    ]);
    $inactive = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'first_name' => 'Cyril',
        'last_name' => 'Inactive',
    ]);

    foreach ([['09:00', '12:00'], ['14:00', '18:00']] as [$start, $end]) {
        Shift::factory()->create([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
            'worker_id' => $scheduled->getKey(),
            'date' => '2026-08-15',
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }
    $ratedShift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $scheduled->getKey(),
        'date' => '2026-08-10',
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $scheduled->getKey(),
        'shift_id' => $ratedShift->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'scheduled_date' => '2026-08-10',
        'scheduled_start_time' => '09:00',
        'scheduled_end_time' => '17:00',
        'started_at' => '2026-08-10 07:00:00 UTC',
        'ended_at' => '2026-08-10 15:00:00 UTC',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $active->getKey(),
        'created_by_user_id' => $admin->getKey(),
        'active_worker_id' => $active->getKey(),
        'started_at' => '2026-08-15 09:00:00 UTC',
        'ended_at' => null,
    ]);

    $response = $this->be($admin, 'users')->get('/attendance', $this->inertiaHeaders());

    $response->assertOk()
        ->assertJsonCount(2, 'props.attendance_rows')
        ->assertJsonPath('props.attendance_rows.0.worker_id', $scheduled->getKey())
        ->assertJsonCount(2, 'props.attendance_rows.0.shifts')
        ->assertJsonPath('props.attendance_rows.0.shifts.0.start_time', '09:00')
        ->assertJsonPath('props.attendance_rows.0.shifts.1.start_time', '14:00')
        ->assertJsonPath('props.attendance_rows.0.quality.attendance_rating_enabled', false)
        ->assertJsonPath('props.attendance_rows.0.quality.average_score', null)
        ->assertJsonPath('props.attendance_rows.0.quality.evaluated_shifts', null)
        ->assertJsonPath('props.attendance_rows.0.quality.band', null)
        ->assertJsonPath('props.attendance_rows.1.worker_id', $active->getKey())
        ->assertJsonPath('props.attendance_rows.1.status', 'present')
        ->assertJsonPath('props.attendance_rows.1.quality.attendance_rating_enabled', true)
        ->assertJsonPath('props.attendance_rows.1.quality.average_score', null)
        ->assertJsonPath('props.attendance_rows.1.quality.band', null)
        ->assertJsonPath('props.off_schedule_workers.0.id', $inactive->getKey());
});
