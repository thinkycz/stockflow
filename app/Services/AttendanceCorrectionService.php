<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceAudit;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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
            $this->authorize($actor, $store, $worker);
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
            $locked = AttendanceSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $store = Store::query()->whereKey($locked->getStoreId())->firstOrFail();
            $this->authorize($actor, $store, $worker);
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

            return $locked->refresh();
        });
    }

    /**
     * Invalidate an attendance session while retaining its audit history.
     */
    public function void(User $actor, AttendanceSession $session, string $reason): AttendanceSession
    {
        return DB::transaction(function () use ($actor, $session, $reason): AttendanceSession {
            $locked = AttendanceSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $store = Store::query()->whereKey($locked->getStoreId())->firstOrFail();
            $worker = Worker::query()->whereKey($locked->getWorkerId())->firstOrFail();
            $this->authorize($actor, $store, $worker);
            $before = $this->snapshot($locked);
            $now = CarbonImmutable::now('UTC');
            AttendanceBreak::query()->where('attendance_session_id', $locked->getKey())->whereNull('ended_at')
                ->update(['ended_at' => $now, 'active_session_id' => null]);
            $locked->update(['active_worker_id' => null, 'voided_at' => $now, 'voided_by_user_id' => $actor->getKey()]);
            $this->audit($locked, $actor, 'correction_void', $reason, $before, $this->snapshot($locked));

            return $locked->refresh();
        });
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
    private function authorize(User $actor, Store $store, Worker $worker): void
    {
        if (!$actor->isAdmin() || $store->isWarehouse() || $store->getUserId() !== $actor->getKey() || $worker->getUserId() !== $actor->getKey()) {
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
     * Raise a validation-style domain exception.
     */
    private function fail(string $key, string $message): never
    {
        Thrower::default()->message($key, $message)->throw();
    }
}
