<?php

declare(strict_types=1);

use App\Enums\AttendanceDeviationReviewDecisionEnum;
use App\Models\AttendanceSession;
use App\Models\PayrollReport;
use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
use App\Services\AttendanceDeviationReviewService;
use App\Services\AttendanceReportService;
use Carbon\CarbonImmutable;

\test('approval updates the shift and every attendance snapshot with an immutable review', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00', 'hourly_rate' => 100,
    ]);
    $first = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'scheduled_date' => '2026-07-10',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-10 06:20:00', 'ended_at' => '2026-07-10 10:00:00',
    ]);
    $last = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'scheduled_date' => '2026-07-10',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-10 10:30:00', 'ended_at' => '2026-07-10 14:30:00',
    ]);

    $review = (new AttendanceDeviationReviewService())->review(
        $admin,
        $store,
        $shift,
        AttendanceDeviationReviewDecisionEnum::APPROVED,
        'Approved adjusted shift',
        '08:15',
        '16:30',
        false,
        CarbonImmutable::parse('2026-07-10 06:20:00 UTC'),
        CarbonImmutable::parse('2026-07-10 14:30:00 UTC'),
        '08:00',
        '16:00',
    );

    \expect($shift->refresh()->getStartTimeShort())->toBe('08:15')
        ->and($shift->getEndTimeShort())->toBe('16:30')
        ->and($first->refresh()->getScheduledStartTime())->toBe('08:15')
        ->and($first->getScheduledEndTime())->toBe('16:30')
        ->and($last->refresh()->getScheduledStartTime())->toBe('08:15')
        ->and($last->getScheduledEndTime())->toBe('16:30')
        ->and($review->getDecision())->toBe(AttendanceDeviationReviewDecisionEnum::APPROVED)
        ->and(fn() => $review->update(['reason' => 'Changed']))->toThrow(LogicException::class)
        ->and(fn() => $review->delete())->toThrow(LogicException::class);

    $deviations = (new AttendanceReportService())->build($admin, $store, '2026-07', null)['deviations'];
    \expect($deviations)->toHaveCount(1)
        ->and($deviations[0]['status'])->toBe('approved')
        ->and((new App\Services\PayrollReportService())->build($admin, $store, 2026, 7)['payslips'][0]['base_amount'])->toBe(825.0);
});

\test('rejection preserves the shift and a later attendance change reopens the deviation', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    $session = AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'scheduled_date' => '2026-07-10',
        'scheduled_start_time' => '08:00', 'scheduled_end_time' => '16:00',
        'started_at' => '2026-07-10 06:20:00', 'ended_at' => '2026-07-10 14:00:00',
    ]);
    $service = new AttendanceDeviationReviewService();

    $service->review(
        $admin,
        $store,
        $shift,
        AttendanceDeviationReviewDecisionEnum::REJECTED,
        'Keep original plan',
        '08:30',
        '16:30',
        false,
        CarbonImmutable::parse('2026-07-10 06:20:00 UTC'),
        CarbonImmutable::parse('2026-07-10 14:00:00 UTC'),
        '08:00',
        '16:00',
    );

    \expect($shift->refresh()->getStartTimeShort())->toBe('08:00')
        ->and((new AttendanceReportService())->build($admin, $store, '2026-07', null)['deviations'][0]['status'])->toBe('rejected');

    $session->update(['started_at' => '2026-07-10 06:30:00']);
    \expect((new AttendanceReportService())->build($admin, $store, '2026-07', null)['deviations'][0]['status'])->toBe('pending');
});

\test('approval is blocked for closed payroll and rejection remains available', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'started_at' => '2026-07-10 06:20:00', 'ended_at' => '2026-07-10 14:00:00',
    ]);
    PayrollReport::query()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'year' => 2026, 'month' => 7, 'status' => 'closed',
    ]);
    $service = new AttendanceDeviationReviewService();
    $arguments = [
        $admin, $store, $shift, AttendanceDeviationReviewDecisionEnum::APPROVED, 'Adjust plan',
        '08:15', '16:00', false, CarbonImmutable::parse('2026-07-10 06:20:00 UTC'),
        CarbonImmutable::parse('2026-07-10 14:00:00 UTC'), '08:00', '16:00',
    ];

    \expect((new AttendanceReportService())->build($admin, $store, '2026-07', null)['deviations'][0]['can_approve'])->toBeFalse();
    \expect(fn() => $service->review(...$arguments))->toThrow(Illuminate\Validation\ValidationException::class);

    $arguments[3] = AttendanceDeviationReviewDecisionEnum::REJECTED;
    \expect($service->review(...$arguments)->getDecision())->toBe(AttendanceDeviationReviewDecisionEnum::REJECTED);
});

\test('approval requires fresh boundaries and an explicit overlap override', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '16:00', 'end_time' => '19:00',
    ]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'started_at' => '2026-07-10 06:20:00', 'ended_at' => '2026-07-10 14:30:00',
    ]);
    $service = new AttendanceDeviationReviewService();
    $arguments = [
        $admin, $store, $shift, AttendanceDeviationReviewDecisionEnum::APPROVED, 'Adjust plan',
        '08:15', '16:30', false, CarbonImmutable::parse('2026-07-10 06:20:00 UTC'),
        CarbonImmutable::parse('2026-07-10 14:30:00 UTC'), '08:00', '16:00',
    ];

    \expect(fn() => $service->review(...$arguments))->toThrow(Illuminate\Validation\ValidationException::class);
    $arguments[7] = true;
    \expect($service->review(...$arguments)->getDecision())->toBe(AttendanceDeviationReviewDecisionEnum::APPROVED);

    $arguments[8] = CarbonImmutable::parse('2026-07-10 06:21:00 UTC');
    \expect(fn() => $service->review(...$arguments))->toThrow(Illuminate\Validation\ValidationException::class);
});

\test('a first review cannot be created for attendance inside the tolerance', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $worker = Worker::factory()->create(['user_id' => $admin->getKey()]);
    $shift = Shift::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'date' => '2026-07-10', 'start_time' => '08:00', 'end_time' => '16:00',
    ]);
    AttendanceSession::factory()->create([
        'user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
        'shift_id' => $shift->getKey(), 'started_at' => '2026-07-10 06:15:00', 'ended_at' => '2026-07-10 14:00:00',
    ]);

    \expect(fn() => (new AttendanceDeviationReviewService())->review(
        $admin,
        $store,
        $shift,
        AttendanceDeviationReviewDecisionEnum::REJECTED,
        'No deviation',
        '08:00',
        '16:00',
        false,
        CarbonImmutable::parse('2026-07-10 06:15:00 UTC'),
        CarbonImmutable::parse('2026-07-10 14:00:00 UTC'),
        '08:00',
        '16:00',
    ))->toThrow(Illuminate\Validation\ValidationException::class);
});
