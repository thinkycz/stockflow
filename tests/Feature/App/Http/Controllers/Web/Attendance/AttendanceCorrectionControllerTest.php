<?php

declare(strict_types=1);

use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\PayrollReport;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use Database\Factories\UserFactory;

\test('admin can create a historical correction and limited user cannot', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $payload = [
        'worker_id' => $worker->getKey(), 'started_at' => '2026-07-20T08:00',
        'ended_at' => '2026-07-20T16:00', 'breaks' => [], 'reason' => 'Doplnění z papíru',
    ];

    $this->be($admin, 'users')->post('/attendance/corrections', $payload, $this->inertiaHeaders())->assertRedirect('/attendance/report');
    \expect(AttendanceSession::query()->count())->toBe(1);

    $limited = UserFactory::new()->limited($store)->createOne();
    $this->be($limited, 'users')->post('/attendance/corrections', $payload, $this->inertiaHeaders())->assertRedirect('/dashboard');
});

\test('admin can update and void an attendance session with audited reasons', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'started_at' => '2026-07-20 06:00:00', 'ended_at' => '2026-07-20 14:00:00',
    ]);

    $this->be($admin, 'users')->put('/attendance/sessions/' . $session->getKey(), [
        'worker_id' => $worker->getKey(), 'started_at' => '2026-07-20T08:15',
        'ended_at' => '2026-07-20T16:15', 'breaks' => [], 'reason' => 'Oprava zápisu',
    ], $this->inertiaHeaders())->assertRedirect('/attendance/report');
    $this->be($admin, 'users')->post('/attendance/sessions/' . $session->getKey() . '/void', [
        'reason' => 'Duplicitní záznam',
    ], $this->inertiaHeaders())->assertRedirect('/attendance/report');

    \expect($session->refresh()->getVoidedAt())->not->toBeNull()
        ->and($session->audits()->pluck('action')->all())->toBe(['correction_update', 'correction_void']);
});

\test('restoring a voided session preserves intervals and audits only once', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'started_at' => '2026-07-20 06:00:00', 'ended_at' => '2026-07-20 14:00:00', 'voided_at' => '2026-07-21 10:00:00',
    ]);
    $url = '/attendance/sessions/' . $session->getKey() . '/restore';
    $this->be($admin, 'users')->from('/attendance/report?month=2026-07&worker_id=' . $worker->getKey())
        ->post($url, ['store_id' => $store->getKey(), 'reason' => 'Valid entry'], $this->inertiaHeaders())
        ->assertRedirect('/attendance/report?month=2026-07&worker_id=' . $worker->getKey());
    $this->post($url, ['store_id' => $store->getKey(), 'reason' => 'Retry'], $this->inertiaHeaders())->assertSessionHasNoErrors();
    \expect($session->refresh()->getVoidedAt())->toBeNull()
        ->and($session->getActiveWorkerId())->toBeNull()
        ->and($session->getEndedAt()?->format('H:i'))->toBe('14:00')
        ->and($session->audits()->where('action', 'correction_restore')->count())->toBe(1);
});

\test('restoring unfinished attendance validates departure and closed breaks atomically', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'started_at' => '2026-07-20 06:00:00', 'ended_at' => null, 'voided_at' => '2026-07-20 14:00:00',
    ]);
    $break = AttendanceBreak::factory()->create(['attendance_session_id' => $session->getKey(), 'started_at' => '2026-07-20 10:00:00', 'ended_at' => '2026-07-20 10:30:00', 'active_session_id' => null]);
    $url = '/attendance/sessions/' . $session->getKey() . '/restore';
    $payload = ['store_id' => $store->getKey(), 'reason' => 'Complete entry'];
    $this->be($admin, 'users')->post($url, $payload, $this->inertiaHeaders())->assertSessionHasErrors('ended_at');
    $this->post($url, [...$payload, 'ended_at' => '2026-07-20T07:00'], $this->inertiaHeaders())->assertSessionHasErrors('ended_at');
    \expect($session->refresh()->getVoidedAt())->not->toBeNull();
    $this->post($url, [...$payload, 'ended_at' => '2026-07-20T16:00'], $this->inertiaHeaders())->assertSessionHasNoErrors();
    \expect($session->refresh()->getVoidedAt())->toBeNull()
        ->and($session->getEndedAt()?->format('H:i'))->toBe('14:00')
        ->and($break->refresh()->getEndedAt()?->format('H:i'))->toBe('10:30');
});

\test('manual matching accepts a same-day shift outside the window and refreshes snapshots idempotently', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'started_at' => '2026-07-20 04:00:00', 'ended_at' => '2026-07-20 12:00:00', 'hourly_rate' => 100,
    ]);
    $shift = Shift::factory()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(), 'date' => '2026-07-20', 'start_time' => '16:00', 'end_time' => '20:00', 'hourly_rate' => 200]);
    $url = '/attendance/sessions/' . $session->getKey() . '/match';
    $payload = ['store_id' => $store->getKey(), 'shift_id' => $shift->getKey(), 'reason' => 'Late schedule'];
    $this->be($admin, 'users')->post($url, $payload, $this->inertiaHeaders())->assertSessionHasNoErrors();
    $this->post($url, $payload, $this->inertiaHeaders())->assertSessionHasNoErrors();
    \expect($session->refresh()->getShiftId())->toBe($shift->getKey())
        ->and($session->getHourlyRate())->toBe(200.0)
        ->and($session->getStartedAt()->format('H:i'))->toBe('04:00')
        ->and($session->audits()->count())->toBe(1);
    $shift->update(['start_time' => '15:00', 'hourly_rate' => 220]);
    $this->post($url, $payload, $this->inertiaHeaders())->assertSessionHasNoErrors();
    \expect($session->refresh()->getHourlyRate())->toBe(220.0)->and($session->audits()->count())->toBe(2);
});

\test('bulk matching respects report filters and skips absent or ambiguous shifts', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $attributes = ['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey()];
    $matched = AttendanceSession::factory()->create([...$attributes, 'started_at' => '2026-07-20 06:00:00', 'ended_at' => '2026-07-20 14:00:00']);
    $ambiguous = AttendanceSession::factory()->create([...$attributes, 'started_at' => '2026-07-21 06:00:00', 'ended_at' => '2026-07-21 14:00:00']);
    $unmatched = AttendanceSession::factory()->create([...$attributes, 'started_at' => '2026-07-22 06:00:00', 'ended_at' => '2026-07-22 14:00:00']);
    $other = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $excluded = AttendanceSession::factory()->create([...$attributes, 'worker_id' => $other->getKey(), 'started_at' => '2026-07-20 06:00:00', 'ended_at' => '2026-07-20 14:00:00']);
    Shift::factory()->create([...$attributes, 'worker_id' => $other->getKey(), 'date' => '2026-07-20', 'start_time' => '08:00', 'end_time' => '16:00']);
    $shift = Shift::factory()->create([...$attributes, 'date' => '2026-07-20', 'start_time' => '08:00', 'end_time' => '16:00']);
    Shift::factory()->count(2)->create([...$attributes, 'date' => '2026-07-21', 'start_time' => '08:00', 'end_time' => '16:00']);
    $this->be($admin, 'users')->post('/attendance/report/match', [...$attributes, 'month' => '2026-07', 'reason' => 'Repair'], $this->inertiaHeaders())->assertSessionHasNoErrors();
    \expect($matched->refresh()->getShiftId())->toBe($shift->getKey())
        ->and($ambiguous->refresh()->getShiftId())->toBeNull()
        ->and($unmatched->refresh()->getShiftId())->toBeNull()
        ->and($excluded->refresh()->getShiftId())->toBeNull();
});

\test('new attendance mutations reject closed payroll and invalid ownership', function (string $action): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $attributes = ['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey()];
    $session = AttendanceSession::factory()->create([...$attributes, 'started_at' => '2026-07-20 06:00:00', 'ended_at' => '2026-07-20 14:00:00', 'voided_at' => $action === 'restore' ? '2026-07-21 00:00:00' : null]);
    $shift = Shift::factory()->create([...$attributes, 'date' => '2026-07-20', 'start_time' => '08:00', 'end_time' => '16:00']);
    PayrollReport::query()->create(['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'year' => 2026, 'month' => 7, 'status' => 'closed']);
    $url = $action === 'bulk' ? '/attendance/report/match' : '/attendance/sessions/' . $session->getKey() . '/' . $action;
    $payload = ['store_id' => $store->getKey(), 'shift_id' => $shift->getKey(), 'month' => '2026-07', 'reason' => 'Repair'];
    $this->be($admin, 'users')->post($url, $payload, $this->inertiaHeaders())->assertSessionHasErrors('payroll');
    \expect($session->audits()->count())->toBe(0);
    $this->post($url, [...$payload, 'reason' => ''], $this->inertiaHeaders())->assertSessionHasErrors('reason');
    $limited = UserFactory::new()->limited($store)->createOne();
    $response = $this->be($limited, 'users')->post($url, $payload, $this->inertiaHeaders());
    if ($action === 'bulk') {
        $response->assertRedirect('/dashboard');
    } else {
        $response->assertNotFound();
    }
})->with(['restore', 'match', 'bulk']);

\test('manual matching rejects a wrong worker store day or voided session', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $attributes = ['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey()];
    $session = AttendanceSession::factory()->create([...$attributes, 'started_at' => '2026-07-20 06:00:00', 'ended_at' => '2026-07-20 14:00:00']);
    $otherWorker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey()]);
    foreach ([['worker_id' => $otherWorker->getKey()], ['store_id' => $otherStore->getKey()], ['date' => '2026-07-21']] as $override) {
        $shift = Shift::factory()->create([...$attributes, 'date' => '2026-07-20', ...$override]);
        $this->be($admin, 'users')->post('/attendance/sessions/' . $session->getKey() . '/match', ['store_id' => $store->getKey(), 'shift_id' => $shift->getKey(), 'reason' => 'Invalid match'], $this->inertiaHeaders())->assertSessionHasErrors('shift_id');
    }
    $shift = Shift::factory()->create([...$attributes, 'date' => '2026-07-20']);
    $session->update(['voided_at' => '2026-07-21 00:00:00']);
    $this->post('/attendance/sessions/' . $session->getKey() . '/match', ['store_id' => $store->getKey(), 'shift_id' => $shift->getKey(), 'reason' => 'Invalid match'], $this->inertiaHeaders())->assertSessionHasErrors('session');
    \expect($session->refresh()->getShiftId())->toBeNull()->and($session->audits()->count())->toBe(0);
});

\test('bulk matching includes month-crossing attendance and leaves voided and existing links alone', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $attributes = ['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey()];
    $shift = Shift::factory()->create([...$attributes, 'date' => '2026-06-30', 'start_time' => '20:00', 'end_time' => '23:59']);
    $crossing = AttendanceSession::factory()->create([...$attributes, 'started_at' => '2026-06-30 18:00:00', 'ended_at' => '2026-07-01 01:00:00']);
    $voided = AttendanceSession::factory()->create([...$attributes, 'started_at' => '2026-06-30 18:00:00', 'ended_at' => '2026-07-01 01:00:00', 'voided_at' => '2026-07-02 00:00:00']);
    $linked = AttendanceSession::factory()->create([...$attributes, 'shift_id' => $shift->getKey(), 'hourly_rate' => 111, 'started_at' => '2026-06-30 18:00:00', 'ended_at' => '2026-07-01 01:00:00']);
    $this->be($admin, 'users')->post('/attendance/report/match', ['store_id' => $store->getKey(), 'month' => '2026-07', 'reason' => 'Match displayed month'], $this->inertiaHeaders())->assertSessionHasNoErrors();
    \expect($crossing->refresh()->getShiftId())->toBe($shift->getKey())
        ->and($voided->refresh()->getShiftId())->toBeNull()
        ->and($linked->refresh()->getHourlyRate())->toBe(111.0)
        ->and($linked->audits()->count())->toBe(0);
});
