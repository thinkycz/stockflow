<?php

declare(strict_types=1);

use App\Domain\Workforce\AttendanceReportService;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use Illuminate\Support\Carbon;

\test('report subtracts breaks and calculates plan difference and wage', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'calendar_color' => '#12ABEF',
    ]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00', 'hourly_rate' => 250,
    ]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'scheduled_date' => '2026-07-10', 'scheduled_start_time' => '08:00',
        'scheduled_end_time' => '16:00', 'hourly_rate' => 250, 'started_at' => '2026-07-10 06:00:00',
        'ended_at' => '2026-07-10 10:00:00',
    ]);
    AttendanceBreak::factory()->create([
        'attendance_session_id' => $session->getKey(), 'started_at' => '2026-07-10 08:00:00',
        'ended_at' => '2026-07-10 08:15:00',
    ]);

    $report = (new AttendanceReportService())->build($admin, $store, '2026-07', null);

    \expect($report['rows'][0]['worker_color'])->toBe('#12ABEF')
        ->and($report['rows'][0]['actual_seconds'])->toBe(13_500)
        ->and($report['rows'][0]['break_seconds'])->toBe(900)
        ->and($report['rows'][0]['breaks'])->toHaveCount(1)
        ->and($report['rows'][0]['breaks'][0]['started_at'])->toContain('2026-07-10T10:00:00')
        ->and($report['rows'][0]['breaks'][0]['ended_at'])->toContain('2026-07-10T10:15:00')
        ->and($report['rows'][0]['breaks'][0]['seconds'])->toBe(900)
        ->and($report['rows'][0]['planned_seconds'])->toBe(28_800)
        ->and($report['rows'][0]['difference_seconds'])->toBe(-15_300)
        ->and($report['rows'][0]['wage'])->toBe(937.5);
});

\test('store occupancy aggregates all workers and stale sessions win', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $workerA = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $workerB = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Carbon::setTestNow('2026-07-21 10:00:00 UTC');
    $sessionA = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $workerA->getKey(),
        'active_worker_id' => $workerA->getKey(), 'started_at' => '2026-07-21 08:00:00', 'ended_at' => null,
    ]);
    $sessionB = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $workerB->getKey(),
        'active_worker_id' => $workerB->getKey(), 'started_at' => '2026-07-21 08:00:00', 'ended_at' => null,
    ]);
    AttendanceBreak::factory()->create(['attendance_session_id' => $sessionA->getKey(), 'active_session_id' => $sessionA->getKey(), 'started_at' => '2026-07-21 09:00:00', 'ended_at' => null]);
    $service = new AttendanceReportService();
    \expect($service->storeState($admin, $store))->toBe('occupied');
    AttendanceBreak::factory()->create(['attendance_session_id' => $sessionB->getKey(), 'active_session_id' => $sessionB->getKey(), 'started_at' => '2026-07-21 09:00:00', 'ended_at' => null]);
    \expect($service->storeState($admin, $store))->toBe('empty');
    $sessionA->update(['started_at' => '2026-07-20 08:00:00']);
    \expect($service->storeState($admin, $store))->toBe('unclear');
    Carbon::setTestNow();
});

\test('report counts only the part of a session inside the requested month', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'started_at' => '2026-07-31 21:30:00', 'ended_at' => '2026-07-31 22:30:00', 'hourly_rate' => 200,
    ]);
    $service = new AttendanceReportService();

    \expect($service->build($admin, $store, '2026-07', null)['rows'][0]['actual_seconds'])->toBe(1800)
        ->and($service->build($admin, $store, '2026-08', null)['rows'][0]['actual_seconds'])->toBe(1800);
});

\test('report requires a deviation review only beyond fifteen minutes', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $withinTolerance = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    $pending = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-11', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $withinTolerance->getKey(), 'scheduled_date' => '2026-07-10',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-10 06:15:00', 'ended_at' => '2026-07-10 14:00:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $pending->getKey(), 'scheduled_date' => '2026-07-11',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-11 06:15:01', 'ended_at' => '2026-07-11 14:00:00',
    ]);

    $report = (new AttendanceReportService())->build($admin, $store, '2026-07', null);

    \expect($report['deviations'])->toHaveCount(1)
        ->and($report['deviations'][0]['shift_id'])->toBe($pending->getKey())
        ->and($report['deviations'][0]['status'])->toBe('pending')
        ->and($report['deviations'][0]['arrival_offset_seconds'])->toBe(901)
        ->and($report['deviations'][0]['departure_offset_seconds'])->toBe(0);
});
