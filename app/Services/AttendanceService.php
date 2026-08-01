<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceActionEnum;
use App\Enums\OperationalActivityTypeEnum;
use App\Models\AttendanceAudit;
use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class AttendanceService
{
    public const string BUSINESS_TIMEZONE = 'Europe/Prague';

    /**
     * List active current-day employees for the actor's store.
     *
     * @return list<array{worker_id: int, worker_name: string, worked_seconds: int, is_on_break: bool}>
     */
    public function activeCurrentDayEmployees(User $actor, Store $store): array
    {
        $sessions = $this->activeCurrentDaySessions($actor, $store);
        $now = CarbonImmutable::now('UTC');
        $breakQuery = AttendanceBreak::query();
        AttendanceBreak::querySelect($breakQuery);
        $breaks = $breakQuery
            ->whereIn('attendance_session_id', $sessions->modelKeys())
            ->get()
            ->groupBy(static fn(AttendanceBreak $break): int => $break->getAttendanceSessionId());
        /** @var array<int, array{worked_seconds: int, is_on_break: bool}> $timings */
        $timings = [];
        foreach ($sessions as $session) {
            $breakSeconds = 0;
            $isOnBreak = false;
            foreach ($breaks->get($session->getKey(), []) as $break) {
                $endedAt = $break->getEndedAt();
                $breakSeconds += (int) $break->getStartedAt()->diffInSeconds($endedAt ?? $now);
                $isOnBreak = $isOnBreak || $endedAt === null;
            }
            $timings[$session->getWorkerId()] = [
                'worked_seconds' => \max(
                    0,
                    (int) $session->getStartedAt()->diffInSeconds($now) - $breakSeconds,
                ),
                'is_on_break' => $isOnBreak,
            ];
        }

        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $actor->resolveScopeUser());
        Worker::querySelect($workerQuery);

        return \array_values($workerQuery
            ->whereIn('id', $sessions->map(
                static fn(AttendanceSession $session): int => $session->getWorkerId(),
            )->all())
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(static function (Worker $worker) use ($timings): array {
                $timing = $timings[$worker->getKey()];

                return [
                    'worker_id' => $worker->getKey(),
                    'worker_name' => $worker->getFullName(),
                    'worked_seconds' => $timing['worked_seconds'],
                    'is_on_break' => $timing['is_on_break'],
                ];
            })
            ->all());
    }

    /**
     * Find active sessions that started on the current Prague business day.
     *
     * @return Collection<int, AttendanceSession>
     */
    public function activeCurrentDaySessions(User $actor, Store $store, bool $lockForUpdate = false): Collection
    {
        $today = CarbonImmutable::now(self::BUSINESS_TIMEZONE);
        $query = AttendanceSession::query();
        AttendanceSession::scopeForUser($query, $actor->resolveScopeUser());
        AttendanceSession::scopeForStore($query, $store->getKey());
        AttendanceSession::querySelect($query);
        $query
            ->whereNotNull('active_worker_id')
            ->where('started_at', '>=', $today->startOfDay()->utc())
            ->where('started_at', '<', $today->addDay()->startOfDay()->utc())
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Close every active current-day attendance at the actor's authorized store.
     */
    public function closeActiveCurrentDayAttendances(User $actor, Store $store): void
    {
        $scopeUser = $actor->resolveScopeUser();
        if ($store->isWarehouse() || $store->getUserId() !== $scopeUser->getKey() ||
            (!$actor->isAdmin() && $actor->getAssignedStoreId() !== $store->getKey())) {
            \abort(403);
        }

        DB::transaction(function () use ($actor, $store): void {
            $sessions = $this->activeCurrentDaySessions($actor, $store, true);
            $workerQuery = Worker::query();
            Worker::scopeForUser($workerQuery, $actor->resolveScopeUser());
            Worker::querySelect($workerQuery);
            $workers = $workerQuery
                ->whereIn('id', $sessions->map(
                    static fn(AttendanceSession $session): int => $session->getWorkerId(),
                )->all())
                ->get()
                ->keyBy(static fn(Worker $worker): int => $worker->getKey());

            foreach ($sessions as $session) {
                $this->perform(
                    $actor,
                    $store,
                    Typer::assertInstance($workers->get($session->getWorkerId()), Worker::class),
                    AttendanceActionEnum::DEPARTURE,
                );
            }
        });
    }

    /**
     * Apply one transactional attendance state transition.
     */
    public function perform(User $actor, Store $store, Worker $worker, AttendanceActionEnum $action, bool $allowWithoutShift = false): AttendanceSession
    {
        return DB::transaction(function () use ($actor, $store, $worker, $action, $allowWithoutShift): AttendanceSession {
            $scopeUser = $actor->resolveScopeUser();
            if ($store->isWarehouse() || $store->getUserId() !== $scopeUser->getKey() || $worker->getUserId() !== $scopeUser->getKey() ||
                (!$actor->isAdmin() && $actor->getAssignedStoreId() !== $store->getKey())) {
                $this->fail('store_id', Typer::assertString(\__('Attendance is not available for this store.')));
            }
            $now = CarbonImmutable::now('UTC');
            $active = AttendanceSession::query()->where('active_worker_id', $worker->getKey())->lockForUpdate()->first();

            if ($action === AttendanceActionEnum::ARRIVAL) {
                if ($active instanceof AttendanceSession) {
                    $this->fail('action', Typer::assertString(\__('This worker already has an open attendance session.')));
                }

                $shift = $this->findMatchingShift($scopeUser, $store, $worker, $now);
                if (!$shift instanceof Shift && !$allowWithoutShift) {
                    $this->fail('confirm_without_shift', Typer::assertString(\__('This worker has no current shift.')));
                }

                $session = AttendanceSession::query()->create([
                    'user_id' => $scopeUser->getKey(),
                    'store_id' => $store->getKey(),
                    'worker_id' => $worker->getKey(),
                    'shift_id' => $shift?->getKey(),
                    'created_by_user_id' => $actor->getKey(),
                    'active_worker_id' => $worker->getKey(),
                    'scheduled_date' => $shift?->getDate(),
                    'scheduled_start_time' => $shift?->getStartTime(),
                    'scheduled_end_time' => $shift?->getEndTime(),
                    'hourly_rate' => $shift?->getHourlyRate() ?? $worker->getHourlyRate(),
                    'started_at' => $now,
                    'ended_at' => null,
                    'voided_at' => null,
                    'voided_by_user_id' => null,
                ]);
                $this->audit($session, $actor, $action->value);
                $this->notify($actor, $store, $worker, $action, $now);

                return $session;
            }

            if (!$active instanceof AttendanceSession || $active->getStoreId() !== $store->getKey()) {
                $this->fail('action', Typer::assertString(\__('This worker has no open attendance session at this store.')));
            }

            if ($active->getStartedAt()->setTimezone(self::BUSINESS_TIMEZONE)->toDateString() !== $now->setTimezone(self::BUSINESS_TIMEZONE)->toDateString()) {
                $this->fail('action', Typer::assertString(\__('This attendance session is stale and must be corrected by an administrator.')));
            }

            $openBreak = AttendanceBreak::query()->where('active_session_id', $active->getKey())->lockForUpdate()->first();
            if ($action === AttendanceActionEnum::BREAK_START) {
                if ($openBreak instanceof AttendanceBreak) {
                    $this->fail('action', Typer::assertString(\__('This worker is already on a break.')));
                }
                AttendanceBreak::query()->create([
                    'attendance_session_id' => $active->getKey(),
                    'created_by_user_id' => $actor->getKey(),
                    'active_session_id' => $active->getKey(),
                    'started_at' => $now,
                ]);
            } elseif ($action === AttendanceActionEnum::BREAK_END) {
                if (!$openBreak instanceof AttendanceBreak) {
                    $this->fail('action', Typer::assertString(\__('This worker is not on a break.')));
                }
                $openBreak->update(['ended_at' => $now, 'active_session_id' => null]);
            } else {
                if ($openBreak instanceof AttendanceBreak) {
                    $openBreak->update(['ended_at' => $now, 'active_session_id' => null]);
                }
                $active->update(['ended_at' => $now, 'active_worker_id' => null]);
            }

            $this->audit($active, $actor, $action->value);

            $this->notify($actor, $store, $worker, $action, $now);

            return $active->refresh();
        });
    }

    /**
     * Find the first shift in the configured matching window.
     */
    public function findMatchingShift(User $owner, Store $store, Worker $worker, CarbonImmutable $now): Shift|null
    {
        $local = $now->setTimezone(self::BUSINESS_TIMEZONE);
        $query = Shift::query();
        Shift::scopeForUser($query, $owner);
        Shift::scopeForStore($query, $store->getKey());
        Shift::scopeForWorker($query, $worker->getKey());
        Shift::scopeForMonth($query, $local->year, $local->month);
        Shift::querySelect($query);

        foreach ($query->whereDate('date', $local->toDateString())->orderBy('start_time')->get() as $shift) {
            if ($this->matchesCurrentWindow($shift, $now)) {
                return $shift;
            }
        }

        return null;
    }

    /**
     * Determine whether the supplied instant is inside a shift's attendance
     * matching window.
     */
    public function matchesCurrentWindow(Shift $shift, CarbonImmutable $now): bool
    {
        $local = $now->setTimezone(self::BUSINESS_TIMEZONE);
        $start = CarbonImmutable::parse(
            $shift->getDate() . ' ' . $shift->getStartTime(),
            self::BUSINESS_TIMEZONE,
        );
        $end = CarbonImmutable::parse(
            $shift->getDate() . ' ' . $shift->getEndTime(),
            self::BUSINESS_TIMEZONE,
        );

        return $local->betweenIncluded($start->subHour(), $end->addHour());
    }

    /**
     * Append an immutable audit event for a normal transition.
     */
    private function audit(AttendanceSession $session, User $actor, string $action): void
    {
        AttendanceAudit::query()->create([
            'attendance_session_id' => $session->getKey(),
            'actor_user_id' => $actor->getKey(),
            'action' => $action,
            'after_state' => ['session_id' => $session->getKey()],
        ]);
    }

    /**
     * Dispatch a committed attendance activity for the store channel.
     */
    private function notify(User $actor, Store $store, Worker $worker, AttendanceActionEnum $action, CarbonImmutable $occurredAt): void
    {
        OperationalActivityService::dispatch(
            match ($action) {
                AttendanceActionEnum::ARRIVAL => OperationalActivityTypeEnum::ATTENDANCE_ARRIVAL,
                AttendanceActionEnum::BREAK_START => OperationalActivityTypeEnum::ATTENDANCE_BREAK_STARTED,
                AttendanceActionEnum::BREAK_END => OperationalActivityTypeEnum::ATTENDANCE_BREAK_ENDED,
                AttendanceActionEnum::DEPARTURE => OperationalActivityTypeEnum::ATTENDANCE_DEPARTURE,
            },
            $actor,
            $occurredAt->toIso8601String(),
            Resolver::resolveUrlGenerator()->route('attendance.index'),
            [['store' => $store, 'perspective' => null]],
            [
                'Slack worker' => $worker->getFullName(),
                'Slack attendance date' => $occurredAt->setTimezone(self::BUSINESS_TIMEZONE)->toDateString(),
            ],
        );
    }

    /**
     * Throw a validation error through the core helper.
     */
    private function fail(string $key, string $message): never
    {
        Thrower::default()->message($key, $message)->throw();
    }
}
