<?php

declare(strict_types=1);

use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use App\Services\AttendanceRatingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

\test('rating gives a completed punctual shift the full score', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'scheduled_date' => '2026-07-10',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-10 06:05:00', 'ended_at' => '2026-07-10 14:00:00',
    ]);
    AttendanceBreak::factory()->create([
        'attendance_session_id' => $session->getKey(),
        'started_at' => '2026-07-10 10:00:00', 'ended_at' => '2026-07-10 10:30:00',
    ]);
    Carbon::setTestNow('2026-07-11 10:00:00 UTC');

    /** @var Collection<int, Shift> $shifts */
    $shifts = Shift::query()->whereKey($shift->getKey())->get();
    $result = (new AttendanceRatingService())->build($admin, $store, $shifts);
    $rating = $result['ratings'][$shift->getKey()];

    \expect($rating['state'])->toBe('scored')
        ->and($rating['score'])->toBe(100)
        ->and($rating['band'])->toBe('good')
        ->and($rating['reason_codes'])->toBe([])
        ->and($rating['arrival_offset_minutes'])->toBe(5)
        ->and($rating['departure_offset_minutes'])->toBe(0)
        ->and($rating['break_minutes'])->toBe(30)
        ->and($rating['break_count'])->toBe(1)
        ->and($result['summary'][0]['average_score'])->toBe(100)
        ->and($result['summary'][0]['good_shifts'])->toBe(1);

    Carbon::setTestNow();
});

\test('disabled attendance rating hides historical metrics and restores them when enabled', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create([
        'user_id' => $admin->getKey(),
        'attendance_rating_enabled' => false,
    ]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'scheduled_date' => '2026-07-10',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-10 06:00:00', 'ended_at' => '2026-07-10 14:00:00',
    ]);
    Carbon::setTestNow('2026-07-11 10:00:00 UTC');

    /** @var Collection<int, Shift> $shifts */
    $shifts = Shift::query()->whereKey($shift->getKey())->get();
    $disabled = (new AttendanceRatingService())->build($admin, $store, $shifts);

    \expect($disabled['ratings'][$shift->getKey()])->toMatchArray([
        'state' => 'disabled',
        'score' => null,
        'band' => null,
        'reason_codes' => [],
        'arrival_offset_minutes' => null,
        'departure_offset_minutes' => null,
        'break_minutes' => null,
        'break_count' => null,
    ])->and($disabled['summary'][0])->toMatchArray([
        'worker_id' => $worker->getKey(),
        'attendance_rating_enabled' => false,
        'average_score' => null,
        'evaluated_shifts' => null,
        'good_shifts' => null,
        'late_arrivals' => null,
        'early_departures' => null,
        'break_issues' => null,
        'absences' => null,
    ]);

    $worker->update(['attendance_rating_enabled' => true]);
    $enabled = (new AttendanceRatingService())->build($admin, $store, $shifts);

    \expect(AttendanceSession::query()->where('worker_id', $worker->getKey())->count())->toBe(1)
        ->and($enabled['ratings'][$shift->getKey()]['state'])->toBe('scored')
        ->and($enabled['ratings'][$shift->getKey()]['score'])->toBe(100)
        ->and($enabled['summary'][0]['attendance_rating_enabled'])->toBeTrue()
        ->and($enabled['summary'][0]['average_score'])->toBe(100);

    Carbon::setTestNow();
});

\test('rating applies all attendance penalties and reports their reasons', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'scheduled_date' => '2026-07-10',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-10 06:11:00', 'ended_at' => '2026-07-10 13:50:00',
    ]);
    foreach ([['08:00:00', '08:15:00'], ['10:00:00', '10:15:00'], ['12:00:00', '12:15:00']] as [$start, $end]) {
        AttendanceBreak::factory()->create([
            'attendance_session_id' => $session->getKey(),
            'started_at' => '2026-07-10 ' . $start, 'ended_at' => '2026-07-10 ' . $end,
        ]);
    }
    Carbon::setTestNow('2026-07-11 10:00:00 UTC');

    /** @var Collection<int, Shift> $shifts */
    $shifts = Shift::query()->whereKey($shift->getKey())->get();
    $result = (new AttendanceRatingService())->build($admin, $store, $shifts);
    $rating = $result['ratings'][$shift->getKey()];

    \expect($rating['score'])->toBe(58)
        ->and($rating['band'])->toBe('poor')
        ->and($rating['reason_codes'])->toBe([
            'late_arrival', 'early_departure', 'excessive_break_duration', 'excessive_break_count',
        ])
        ->and($rating['arrival_offset_minutes'])->toBe(11)
        ->and($rating['departure_offset_minutes'])->toBe(-10)
        ->and($rating['break_minutes'])->toBe(45)
        ->and($result['summary'][0]['late_arrivals'])->toBe(1)
        ->and($result['summary'][0]['early_departures'])->toBe(1)
        ->and($result['summary'][0]['break_issues'])->toBe(1);

    Carbon::setTestNow();
});

\test('rating distinguishes future pending absent and voided attendance', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $future = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-12', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    $pending = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $pending->getKey(), 'active_worker_id' => $worker->getKey(),
        'scheduled_date' => '2026-07-10', 'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-10 06:00:00', 'ended_at' => null,
    ]);
    $currentPending = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-11', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $currentPending->getKey(), 'active_worker_id' => null,
        'scheduled_date' => '2026-07-11', 'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-11 06:00:00', 'ended_at' => null,
    ]);
    $absent = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-09', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    $voided = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-08', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $voided->getKey(), 'scheduled_date' => '2026-07-08',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-08 06:00:00', 'ended_at' => '2026-07-08 14:00:00',
        'voided_at' => '2026-07-09 10:00:00',
    ]);
    Carbon::setTestNow('2026-07-11 10:00:00 UTC');

    /** @var Collection<int, Shift> $shifts */
    $shifts = Shift::query()->whereIn('id', [
        $future->getKey(), $pending->getKey(), $currentPending->getKey(), $absent->getKey(), $voided->getKey(),
    ])->get();
    $result = (new AttendanceRatingService())->build($admin, $store, $shifts);

    \expect($result['ratings'][$future->getKey()]['state'])->toBe('future')
        ->and($result['ratings'][$pending->getKey()]['state'])->toBe('pending')
        ->and($result['ratings'][$currentPending->getKey()]['state'])->toBe('pending')
        ->and($result['ratings'][$absent->getKey()]['score'])->toBe(0)
        ->and($result['ratings'][$absent->getKey()]['reason_codes'])->toBe(['absence'])
        ->and($result['ratings'][$voided->getKey()]['score'])->toBe(0)
        ->and($result['summary'][0]['evaluated_shifts'])->toBe(2)
        ->and($result['summary'][0]['absences'])->toBe(2)
        ->and($result['summary'][0]['average_score'])->toBe(0);

    Carbon::setTestNow();
});

\test('rating counts gaps between multiple attendance blocks as breaks', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    $first = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'scheduled_date' => '2026-07-10',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-10 06:00:00', 'ended_at' => '2026-07-10 10:00:00',
    ]);
    AttendanceBreak::factory()->create([
        'attendance_session_id' => $first->getKey(),
        'started_at' => '2026-07-10 08:00:00', 'ended_at' => '2026-07-10 08:10:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'scheduled_date' => '2026-07-10',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-10 10:20:00', 'ended_at' => '2026-07-10 14:00:00',
    ]);
    Carbon::setTestNow('2026-07-11 10:00:00 UTC');

    /** @var Collection<int, Shift> $shifts */
    $shifts = Shift::query()->whereKey($shift->getKey())->get();
    $rating = (new AttendanceRatingService())->build($admin, $store, $shifts)['ratings'][$shift->getKey()];

    \expect($rating['score'])->toBe(100)
        ->and($rating['break_minutes'])->toBe(30)
        ->and($rating['break_count'])->toBe(2);

    Carbon::setTestNow();
});

\test('rating uses attendance schedule snapshots and caps every penalty', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $snapshotShift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '09:00', 'end_time' => '17:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $snapshotShift->getKey(), 'scheduled_date' => '2026-07-10',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-10 06:00:00', 'ended_at' => '2026-07-10 14:00:00',
    ]);
    $cappedShift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-11', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    $cappedSession = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $cappedShift->getKey(), 'scheduled_date' => '2026-07-11',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-11 07:00:00', 'ended_at' => '2026-07-11 12:00:00',
    ]);
    foreach ([
        ['07:30:00', '08:00:00'], ['08:10:00', '08:40:00'], ['09:00:00', '09:30:00'],
        ['09:40:00', '10:10:00'], ['10:20:00', '10:50:00'],
    ] as [$start, $end]) {
        AttendanceBreak::factory()->create([
            'attendance_session_id' => $cappedSession->getKey(),
            'started_at' => '2026-07-11 ' . $start, 'ended_at' => '2026-07-11 ' . $end,
        ]);
    }
    Carbon::setTestNow('2026-07-12 10:00:00 UTC');

    /** @var Collection<int, Shift> $shifts */
    $shifts = Shift::query()->whereIn('id', [$snapshotShift->getKey(), $cappedShift->getKey()])->get();
    $ratings = (new AttendanceRatingService())->build($admin, $store, $shifts)['ratings'];

    \expect($ratings[$snapshotShift->getKey()]['score'])->toBe(100)
        ->and($ratings[$cappedShift->getKey()]['score'])->toBe(0)
        ->and($ratings[$cappedShift->getKey()]['reason_codes'])->toBe([
            'late_arrival', 'early_departure', 'excessive_break_duration', 'excessive_break_count',
        ]);

    Carbon::setTestNow();
});
