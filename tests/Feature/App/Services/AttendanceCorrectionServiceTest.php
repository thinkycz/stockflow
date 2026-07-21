<?php

declare(strict_types=1);

use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use App\Services\AttendanceCorrectionService;
use Carbon\CarbonImmutable;

\test('administrator correction replaces times and keeps an immutable audit snapshot', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-20', 'start_time' => '08:00', 'end_time' => '16:00', 'hourly_rate' => 275,
    ]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'active_worker_id' => $worker->getKey(), 'started_at' => '2026-07-20 06:00:00', 'ended_at' => null,
    ]);

    (new AttendanceCorrectionService())->update(
        $admin,
        $session,
        $worker,
        CarbonImmutable::parse('2026-07-20 06:05:00 UTC'),
        CarbonImmutable::parse('2026-07-20 14:00:00 UTC'),
        [['started_at' => CarbonImmutable::parse('2026-07-20 10:00:00 UTC'), 'ended_at' => CarbonImmutable::parse('2026-07-20 10:15:00 UTC')]],
        'Zapomenutý odchod',
    );

    $audit = $session->audits()->where('action', 'correction_update')->firstOrFail();
    \expect($session->refresh()->getActiveWorkerId())->toBeNull()
        ->and($session->getEndedAt()?->toDateTimeString())->toBe('2026-07-20 14:00:00')
        ->and($session->attendanceBreaks()->count())->toBe(1)
        ->and($session->getShiftId())->toBe($shift->getKey())
        ->and($session->getHourlyRate())->toBe(275.0)
        ->and($audit->getAttribute('reason'))->toBe('Zapomenutý odchod')
        ->and($audit->getAttribute('before_state'))->not->toBeNull()
        ->and($audit->getAttribute('after_state'))->not->toBeNull();
});
