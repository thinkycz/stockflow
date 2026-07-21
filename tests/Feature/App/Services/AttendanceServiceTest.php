<?php

declare(strict_types=1);

use App\Enums\AttendanceActionEnum;
use App\Models\AttendanceAudit;
use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

\afterEach(function (): void {
    Carbon::setTestNow();
});

\test('worker can take a break return and depart with exact timestamps', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $service = new AttendanceService();
    Carbon::setTestNow('2026-07-21 08:00:00 UTC');
    $session = $service->perform($admin, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    Carbon::setTestNow('2026-07-21 10:00:00 UTC');
    $service->perform($admin, $store, $worker, AttendanceActionEnum::BREAK_START);
    Carbon::setTestNow('2026-07-21 10:15:00 UTC');
    $service->perform($admin, $store, $worker, AttendanceActionEnum::BREAK_END);
    Carbon::setTestNow('2026-07-21 12:00:00 UTC');
    $closed = $service->perform($admin, $store, $worker, AttendanceActionEnum::DEPARTURE);

    \expect($closed->getEndedAt()?->toDateTimeString())->toBe('2026-07-21 12:00:00')
        ->and($closed->getActiveWorkerId())->toBeNull()
        ->and($session->attendanceBreaks()->first()?->getEndedAt()?->toDateTimeString())->toBe('2026-07-21 10:15:00')
        ->and($session->audits()->count())->toBe(4);
});

\test('departure directly from a break closes both records', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $service = new AttendanceService();
    Carbon::setTestNow('2026-07-21 08:00:00 UTC');
    $session = $service->perform($admin, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    Carbon::setTestNow('2026-07-21 10:00:00 UTC');
    $service->perform($admin, $store, $worker, AttendanceActionEnum::BREAK_START);
    Carbon::setTestNow('2026-07-21 11:00:00 UTC');
    $service->perform($admin, $store, $worker, AttendanceActionEnum::DEPARTURE);

    \expect($session->attendanceBreaks()->first()?->getEndedAt())->not->toBeNull()
        ->and($session->refresh()->getEndedAt())->not->toBeNull();
});

\test('arrival without a matching shift requires confirmation', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Carbon::setTestNow('2026-07-21 08:00:00 UTC');

    \expect(fn(): AttendanceSession => (new AttendanceService())->perform($admin, $store, $worker, AttendanceActionEnum::ARRIVAL))
        ->toThrow(ValidationException::class);
});

\test('stale open session blocks state changes', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $service = new AttendanceService();
    Carbon::setTestNow('2026-07-20 08:00:00 UTC');
    $service->perform($admin, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    Carbon::setTestNow('2026-07-21 08:00:00 UTC');

    \expect(fn(): AttendanceSession => $service->perform($admin, $store, $worker, AttendanceActionEnum::DEPARTURE))
        ->toThrow(ValidationException::class);
});

\test('arrival opens a session and snapshots the matching shift', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey(), 'hourly_rate' => 300]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'worker_id' => $worker->getKey(),
        'date' => '2026-07-21',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'hourly_rate' => 250,
    ]);
    Carbon::setTestNow('2026-07-21 06:30:00 UTC');

    $session = (new AttendanceService())->perform($admin, $store, $worker, AttendanceActionEnum::ARRIVAL);

    \expect($session)->toBeInstanceOf(AttendanceSession::class)
        ->and($session->getShiftId())->toBe($shift->getKey())
        ->and($session->getHourlyRate())->toBe(250.0)
        ->and($session->getActiveWorkerId())->toBe($worker->getKey())
        ->and($session->getEndedAt())->toBeNull();
});

\test('limited user cannot record attendance for another store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $assignedStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $otherStore = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $limited = UserFactory::new()->limited($assignedStore)->createOne();

    \expect(fn(): AttendanceSession => (new AttendanceService())->perform(
        $limited,
        $otherStore,
        $worker,
        AttendanceActionEnum::ARRIVAL,
        true,
    ))->toThrow(ValidationException::class);
});

\test('attendance audit records cannot be modified or deleted', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $session = (new AttendanceService())->perform($admin, $store, $worker, AttendanceActionEnum::ARRIVAL, true);
    $audit = $session->audits()->firstOrFail();
    \expect($audit)->toBeInstanceOf(AttendanceAudit::class)
        ->and(fn(): bool => $audit->update(['action' => 'changed']))->toThrow(LogicException::class)
        ->and(fn(): bool => $audit->delete())->toThrow(LogicException::class);
});

\test('duplicate arrival and return without a break are rejected', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $service = new AttendanceService();
    $service->perform($admin, $store, $worker, AttendanceActionEnum::ARRIVAL, true);

    \expect(fn(): AttendanceSession => $service->perform($admin, $store, $worker, AttendanceActionEnum::ARRIVAL, true))
        ->toThrow(ValidationException::class)
        ->and(fn(): AttendanceSession => $service->perform($admin, $store, $worker, AttendanceActionEnum::BREAK_END))
        ->toThrow(ValidationException::class);
});

\test('shift matching includes both sixty minute boundaries and excludes later times', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-21', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    $service = new AttendanceService();

    \expect($service->findMatchingShift($admin, $store, $worker, CarbonImmutable::parse('2026-07-21 05:00:00 UTC'))?->getKey())->toBe($shift->getKey())
        ->and($service->findMatchingShift($admin, $store, $worker, CarbonImmutable::parse('2026-07-21 15:00:00 UTC'))?->getKey())->toBe($shift->getKey())
        ->and($service->findMatchingShift($admin, $store, $worker, CarbonImmutable::parse('2026-07-21 15:00:01 UTC')))->toBeNull();
});
