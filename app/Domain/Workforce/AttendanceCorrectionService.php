<?php

declare(strict_types=1);

namespace App\Domain\Workforce;

use App\Enums\OperationalActivityTypeEnum;
use App\Models\AttendanceAudit;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\PayrollReport;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Support\OperationalActivityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class AttendanceCorrectionService
{
    /**
     * Create a completed attendance session through an audited admin correction.
     *
     * @param list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}> $breaks
     */
    public function create(User $actor, Store $store, Worker $worker, CarbonImmutable $startedAt, CarbonImmutable $endedAt, array $breaks, string $reason): AttendanceSession
    {
        return DB::transaction(function () use ($actor, $store, $worker, $startedAt, $endedAt, $breaks, $reason): AttendanceSession {
            $store = Typer::assertInstance(
                Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $worker = Typer::assertInstance(
                Worker::query()->whereKey($worker->getKey())->lockForUpdate()->firstOrFail(),
                Worker::class,
            );
            $this->authorize($actor, $store, $worker, true);
            $this->validateIntervals($startedAt, $endedAt, $breaks);
            $shift = (new AttendanceService())->findMatchingShift($actor, $store, $worker, $startedAt);
            $session = AttendanceSession::query()->create([
                'user_id' => $actor->getKey(), 'store_id' => $store->getKey(), 'worker_id' => $worker->getKey(),
                'shift_id' => $shift?->getKey(), 'created_by_user_id' => $actor->getKey(), 'active_worker_id' => null,
                'scheduled_date' => $shift?->getDate(), 'scheduled_start_time' => $shift?->getStartTime(), 'scheduled_end_time' => $shift?->getEndTime(),
                'hourly_rate' => $shift?->getHourlyRate() ?? $worker->getHourlyRate(), 'started_at' => $startedAt, 'ended_at' => $endedAt,
                'voided_at' => null, 'voided_by_user_id' => null,
            ]);
            $this->replaceBreaks($session, $actor, $breaks);
            $this->audit($session, $actor, 'correction_create', $reason, null, $this->snapshot($session));
            $this->notify($actor, $store, $worker, $session, OperationalActivityTypeEnum::ATTENDANCE_CORRECTION_CREATED);

            return $session;
        });
    }

    /**
     * Replace the editable fields and breaks of an attendance session.
     *
     * @param list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}> $breaks
     */
    public function update(User $actor, AttendanceSession $session, Worker $worker, CarbonImmutable $startedAt, CarbonImmutable $endedAt, array $breaks, string $reason): AttendanceSession
    {
        return DB::transaction(function () use ($actor, $session, $worker, $startedAt, $endedAt, $breaks, $reason): AttendanceSession {
            $store = Typer::assertInstance(
                Store::query()->whereKey($session->getStoreId())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $locked = AttendanceSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $worker = Typer::assertInstance(
                Worker::query()->whereKey($worker->getKey())->lockForUpdate()->firstOrFail(),
                Worker::class,
            );
            $this->authorize($actor, $store, $worker, $worker->getKey() !== $locked->getWorkerId());
            $before = $this->snapshot($locked);
            $this->validateIntervals($startedAt, $endedAt, $breaks);
            $shift = (new AttendanceService())->findMatchingShift($actor, $store, $worker, $startedAt);
            AttendanceBreak::query()->where('attendance_session_id', $locked->getKey())->delete();
            $locked->update([
                'worker_id' => $worker->getKey(), 'active_worker_id' => null,
                'shift_id' => $shift?->getKey(), 'scheduled_date' => $shift?->getDate(),
                'scheduled_start_time' => $shift?->getStartTime(), 'scheduled_end_time' => $shift?->getEndTime(),
                'hourly_rate' => $shift?->getHourlyRate() ?? $worker->getHourlyRate(),
                'started_at' => $startedAt, 'ended_at' => $endedAt,
            ]);
            $this->replaceBreaks($locked, $actor, $breaks);
            $this->audit($locked, $actor, 'correction_update', $reason, $before, $this->snapshot($locked));
            if ($before !== $this->snapshot($locked)) {
                $this->notify($actor, $store, $worker, $locked, OperationalActivityTypeEnum::ATTENDANCE_CORRECTION_UPDATED);
            }

            return $locked->refresh();
        });
    }

    /**
     * Invalidate an attendance session while retaining its audit history.
     */
    public function void(User $actor, AttendanceSession $session, string $reason): AttendanceSession
    {
        return DB::transaction(function () use ($actor, $session, $reason): AttendanceSession {
            $store = Typer::assertInstance(
                Store::query()->whereKey($session->getStoreId())->lockForUpdate()->firstOrFail(),
                Store::class,
            );
            $locked = AttendanceSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $worker = Worker::query()->whereKey($locked->getWorkerId())->firstOrFail();
            $this->authorize($actor, $store, $worker);
            if ($locked->getVoidedAt() !== null) {
                return $locked;
            }
            $before = $this->snapshot($locked);
            $now = CarbonImmutable::now('UTC');
            AttendanceBreak::query()->where('attendance_session_id', $locked->getKey())->whereNull('ended_at')
                ->update(['ended_at' => $now, 'active_session_id' => null]);
            $locked->update(['active_worker_id' => null, 'voided_at' => $now, 'voided_by_user_id' => $actor->getKey()]);
            $this->audit($locked, $actor, 'correction_void', $reason, $before, $this->snapshot($locked));
            $this->notify($actor, $store, $worker, $locked, OperationalActivityTypeEnum::ATTENDANCE_CORRECTION_VOIDED);

            return $locked->refresh();
        });
    }

    /**
     * Restore a voided session, completing an unfinished interval atomically.
     */
    public function restore(User $actor, AttendanceSession $session, string $reason, CarbonImmutable|null $endedAt = null): AttendanceSession
    {
        return DB::transaction(function () use ($actor, $session, $reason, $endedAt): AttendanceSession {
            $locked = $this->lockSession($actor, $session, $reason);
            if ($locked->getVoidedAt() === null) {
                return $locked;
            }
            $end = $locked->getEndedAt()?->toImmutable() ?? $endedAt;
            if ($end === null) {
                $this->fail('ended_at', Typer::assertString(\__('Departure is required before restoring attendance.')));
            }
            $breaks = $locked->attendanceBreaks()->orderBy('started_at')->get()->map(static fn(AttendanceBreak $break): array => [
                'started_at' => $break->getStartedAt()->toImmutable(),
                'ended_at' => $break->getEndedAt()?->toImmutable(),
            ])->all();
            foreach ($breaks as $break) {
                if ($break['ended_at'] === null) {
                    $this->fail('breaks', Typer::assertString(\__('Complete attendance breaks before restoring attendance.')));
                }
            }
            $this->validateIntervals($locked->getStartedAt()->toImmutable(), $end, \array_values($breaks));
            $this->assertPayrollOpen($actor, $locked, $end);
            $before = $this->snapshot($locked);
            $locked->update(['voided_at' => null, 'voided_by_user_id' => null, 'active_worker_id' => null, 'ended_at' => $end]);
            $this->audit($locked, $actor, 'correction_restore', $reason, $before, $this->snapshot($locked));
            $this->notify($actor, Store::query()->whereKey($locked->getStoreId())->firstOrFail(), Worker::query()->whereKey($locked->getWorkerId())->firstOrFail(), $locked, OperationalActivityTypeEnum::ATTENDANCE_CORRECTION_RESTORED);

            return $locked->refresh();
        });
    }

    /**
     * Manually select a shift without changing recorded attendance intervals.
     */
    public function matchShift(User $actor, AttendanceSession $session, int $shiftId, string $reason): AttendanceSession
    {
        return $this->applyShiftMatch($actor, $session, $shiftId, $reason, true);
    }

    /**
     * Match only unambiguous unpaired rows from the current report scope.
     *
     * @return array{matched: int, unmatched: int, ambiguous: int}
     */
    public function matchReport(User $actor, Store $store, string $month, int|null $workerId, string $reason): array
    {
        return DB::transaction(function () use ($actor, $store, $month, $workerId, $reason): array {
            $store = Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail();
            if (!$actor->isAdmin() || $store->getUserId() !== $actor->getKey() || !$store->isActive() || $store->isWarehouse()) {
                \abort(404);
            }
            $result = ['matched' => 0, 'unmatched' => 0, 'ambiguous' => 0];
            $sessions = AttendanceReportService::sessionsQuery($actor, $store, $month, $workerId)
                ->whereNull('voided_at')->whereNull('shift_id')->lockForUpdate()->get();
            foreach ($sessions as $session) {
                $candidates = $this->shiftCandidates($actor, $session)->filter(
                    static fn(Shift $shift): bool => (new AttendanceService())->matchesCurrentWindow($shift, $session->getStartedAt()->toImmutable()),
                );
                if ($candidates->count() !== 1) {
                    ++$result[$candidates->isEmpty() ? 'unmatched' : 'ambiguous'];

                    continue;
                }
                $shift = $candidates->firstOrFail();
                $this->applyShiftMatch($actor, $session, $shift->getKey(), $reason, false);
                ++$result['matched'];
            }

            if ($result['matched'] > 0) {
                OperationalActivityService::dispatchForStore(
                    OperationalActivityTypeEnum::ATTENDANCE_REPORT_MATCHED,
                    $actor,
                    $store,
                    'attendance.report',
                    ['month' => $month],
                    ['Slack report month' => $month, 'Slack affected count' => (string) $result['matched']],
                );
            }

            return $result;
        });
    }

    /**
     * List same-day candidates for one owned attendance.
     *
     * @return Collection<int, Shift>
     */
    public function shiftCandidates(User $actor, AttendanceSession $session): Collection
    {
        $query = Shift::query();
        Shift::scopeForUser($query, $actor);

        return $query->where('store_id', $session->getStoreId())->where('worker_id', $session->getWorkerId())
            ->whereDate('date', $session->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toDateString())
            ->orderBy('start_time')->orderBy('id')->get();
    }

    /**
     * Apply a shift match, optionally journaling the individual action.
     */
    private function applyShiftMatch(User $actor, AttendanceSession $session, int $shiftId, string $reason, bool $notify): AttendanceSession
    {
        return DB::transaction(function () use ($actor, $session, $shiftId, $reason, $notify): AttendanceSession {
            $locked = $this->lockSession($actor, $session, $reason);
            if ($locked->getVoidedAt() !== null) {
                $this->fail('session', Typer::assertString(\__('Restore attendance before matching it.')));
            }
            $shift = Shift::query()->whereKey($shiftId)->lockForUpdate()->firstOrFail();
            if ($shift->getUserId() !== $actor->getKey() || $shift->getStoreId() !== $locked->getStoreId() ||
                $shift->getWorkerId() !== $locked->getWorkerId() ||
                $shift->getDate() !== $locked->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toDateString()) {
                $this->fail('shift_id', Typer::assertString(\__('Select a shift for the same worker, store and attendance day.')));
            }
            $this->assertPayrollOpen($actor, $locked, $locked->getEndedAt()?->toImmutable());
            $before = $this->snapshot($locked);
            $locked->fill([
                'shift_id' => $shift->getKey(), 'scheduled_date' => $shift->getDate(),
                'scheduled_start_time' => $shift->getStartTime(), 'scheduled_end_time' => $shift->getEndTime(),
                'hourly_rate' => $shift->getHourlyRate(),
            ]);
            if ($locked->isDirty()) {
                $locked->save();
                $this->audit($locked, $actor, 'correction_match', $reason, $before, $this->snapshot($locked));
                if ($notify) {
                    $this->notify($actor, Store::query()->whereKey($locked->getStoreId())->firstOrFail(), Worker::query()->whereKey($locked->getWorkerId())->firstOrFail(), $locked, OperationalActivityTypeEnum::ATTENDANCE_SHIFT_MATCHED);
                }
            }

            return $locked->refresh();
        });
    }

    /**
     * Serialize mutations with store lifecycle changes and validate ownership.
     */
    private function lockSession(User $actor, AttendanceSession $session, string $reason): AttendanceSession
    {
        $store = Store::query()->whereKey($session->getStoreId())->lockForUpdate()->firstOrFail();
        $locked = AttendanceSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
        $this->authorize($actor, $store, Worker::query()->whereKey($locked->getWorkerId())->firstOrFail());
        if ($locked->getUserId() !== $actor->getKey() || \mb_trim($reason) === '') {
            $this->fail('reason', Typer::assertString(\__('An attendance correction reason is required.')));
        }

        return $locked;
    }

    /**
     * Prevent changes to any closed payroll period touched by the session.
     */
    private function assertPayrollOpen(User $actor, AttendanceSession $session, CarbonImmutable|null $end): void
    {
        $start = $session->getStartedAt()->toImmutable()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->startOfMonth();
        $last = ($end ?? CarbonImmutable::now())->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->startOfMonth();
        $months = [];
        for ($date = $start; $date->lessThanOrEqualTo($last); $date = $date->addMonth()) {
            $months[] = $date->format('Y-m');
        }
        $months[] = $start->format('Y-m');
        if ($session->getScheduledDate() !== null) {
            $months[] = $session->getScheduledDate()->format('Y-m');
        }
        $query = PayrollReport::query();
        PayrollReport::scopeForUser($query, $actor);
        foreach ($query->where('store_id', $session->getStoreId())->where('status', 'closed')->get() as $report) {
            if (\in_array(\sprintf('%04d-%02d', $report->getYear(), $report->getMonth()), $months, true)) {
                $this->fail('payroll', Typer::assertString(\__('Reopen the payroll report before changing attendance.')));
            }
        }
    }

    /**
     * Ensure corrected work and break intervals form a valid timeline.
     *
     * @param list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}> $breaks
     */
    private function validateIntervals(CarbonImmutable $startedAt, CarbonImmutable $endedAt, array $breaks): void
    {
        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            $this->fail('ended_at', Typer::assertString(\__('Departure must be after arrival.')));
        }
        $lastEnd = $startedAt;
        foreach ($breaks as $break) {
            if ($break['started_at']->lessThan($lastEnd) || $break['ended_at']->lessThanOrEqualTo($break['started_at']) || $break['ended_at']->greaterThan($endedAt)) {
                $this->fail('breaks', Typer::assertString(\__('Attendance breaks must be ordered and contained in the work session.')));
            }
            $lastEnd = $break['ended_at'];
        }
    }

    /**
     * Ensure corrections are made by the owning administrator for a retail store.
     */
    private function authorize(User $actor, Store $store, Worker $worker, bool $requiresActiveWorker = false): void
    {
        if (!$actor->isAdmin() || !$store->isActive() || $store->isWarehouse() || $store->getUserId() !== $actor->getKey() || $worker->getUserId() !== $actor->getKey() || ($requiresActiveWorker && $worker->isArchived())) {
            $this->fail('store_id', Typer::assertString(\__('Attendance correction is not allowed.')));
        }
    }

    /**
     * Persist the complete corrected break list for a session.
     *
     * @param list<array{started_at: CarbonImmutable, ended_at: CarbonImmutable}> $breaks
     */
    private function replaceBreaks(AttendanceSession $session, User $actor, array $breaks): void
    {
        foreach ($breaks as $break) {
            AttendanceBreak::query()->create([
                'attendance_session_id' => $session->getKey(), 'created_by_user_id' => $actor->getKey(),
                'active_session_id' => null, 'started_at' => $break['started_at'], 'ended_at' => $break['ended_at'],
            ]);
        }
    }

    /**
     * Capture the mutable attendance state stored in audit records.
     *
     * @return array<string, mixed>
     */
    private function snapshot(AttendanceSession $session): array
    {
        return [
            'worker_id' => $session->getWorkerId(),
            'shift_id' => $session->getShiftId(),
            'scheduled_date' => $session->getScheduledDate()?->toDateString(),
            'scheduled_start_time' => $session->getScheduledStartTime(),
            'scheduled_end_time' => $session->getScheduledEndTime(),
            'hourly_rate' => $session->getHourlyRate(),
            'started_at' => $session->getStartedAt()->toIso8601String(),
            'ended_at' => $session->getEndedAt()?->toIso8601String(),
            'voided_at' => $session->getVoidedAt()?->toIso8601String(),
            'breaks' => $session->attendanceBreaks()->orderBy('started_at')->get()->map(static fn(AttendanceBreak $break): array => [
                'started_at' => $break->getStartedAt()->toIso8601String(),
                'ended_at' => $break->getEndedAt()?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * Append an immutable correction audit record.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    private function audit(AttendanceSession $session, User $actor, string $action, string $reason, array|null $before, array $after): void
    {
        AttendanceAudit::query()->create([
            'attendance_session_id' => $session->getKey(), 'actor_user_id' => $actor->getKey(),
            'action' => $action, 'reason' => $reason, 'before_state' => $before, 'after_state' => $after,
        ]);
    }

    /**
     * Dispatch a committed attendance correction activity.
     */
    private function notify(User $actor, Store $store, Worker $worker, AttendanceSession $session, OperationalActivityTypeEnum $type): void
    {
        OperationalActivityService::dispatch(
            $type,
            $actor,
            CarbonImmutable::now('UTC')->toIso8601String(),
            Resolver::resolveUrlGenerator()->route('attendance.report', ['store_id' => $store->getKey(), 'month' => $session->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->format('Y-m')]),
            [['store' => $store, 'perspective' => null]],
            [
                'Slack worker' => $worker->getFullName(),
                'Slack attendance date' => $session->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->format('j.n.Y'),
            ],
        );
    }

    /**
     * Raise a validation-style domain exception.
     */
    private function fail(string $key, string $message): never
    {
        Thrower::default()->message($key, $message)->throw();
    }
}
