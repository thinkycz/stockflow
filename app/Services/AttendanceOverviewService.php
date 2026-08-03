<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class AttendanceOverviewService
{
    /**
     * Build the actionable attendance rows for the current Prague business day.
     *
     * @return array{
     *     attendance_rows: list<array{
     *         worker_id: int,
     *         worker_name: string,
     *         status: string,
     *         has_current_shift: bool,
     *         shifts: list<array{id: int, start_time: string, end_time: string}>,
     *         sessions: list<array{
     *             id: int,
     *             started_at: string,
     *             ended_at: string|null,
     *             breaks: list<array{started_at: string, ended_at: string|null}>
     *         }>,
     *         quality: array{
     *             attendance_rating_enabled: bool, average_score: int|null,
     *             evaluated_shifts: int|null, band: string|null
     *         }
     *     }>,
     *     off_schedule_workers: list<array{id: int, name: string}>
     * }
     */
    public function build(User $owner, Store $store): array
    {
        $now = CarbonImmutable::now('UTC');
        $localNow = $now->setTimezone(AttendanceService::BUSINESS_TIMEZONE);
        $workers = $this->workers($owner);
        $workersById = $workers->keyBy(
            fn(Worker $worker): int => $worker->getKey(),
        );
        $monthShifts = $this->monthShifts(
            $owner,
            $store,
            $localNow->year,
            $localNow->month,
        );
        $todayShifts = $monthShifts->filter(
            fn(Shift $shift): bool => $shift->getDate() === $localNow->toDateString(),
        );
        $activeSessions = $this->activeSessions($owner, $store);
        $todaySessions = $this->todaySessions($owner, $store, $localNow);
        $sessions = $todaySessions
            ->merge($activeSessions)
            ->unique(fn(AttendanceSession $session): int => $session->getKey())
            ->values();
        $breaks = $this->breaks($sessions);
        $ratingsByWorker = [];
        foreach ((new AttendanceRatingService())->build($owner, $store, $monthShifts)['summary'] as $rating) {
            $ratingsByWorker[$rating['worker_id']] = $rating;
        }
        $shiftsByWorker = $todayShifts->groupBy(
            fn(Shift $shift): int => $shift->getWorkerId(),
        );
        $sessionsByWorker = $sessions->groupBy(
            fn(AttendanceSession $session): int => $session->getWorkerId(),
        );
        $activeByWorker = $activeSessions->keyBy(
            fn(AttendanceSession $session): int => $session->getWorkerId(),
        );
        $candidateIds = \array_values(\array_unique([
            ...$todayShifts->map(
                fn(Shift $shift): int => $shift->getWorkerId(),
            )->all(),
            ...$activeSessions->map(
                fn(AttendanceSession $session): int => $session->getWorkerId(),
            )->all(),
        ]));
        $attendanceService = new AttendanceService();
        $rows = [];

        foreach ($candidateIds as $workerId) {
            $worker = $workersById->get($workerId);
            if (!$worker instanceof Worker) {
                continue;
            }
            /** @var Collection<int, Shift> $workerShifts */
            $workerShifts = $shiftsByWorker->get($workerId, new Collection());
            /** @var Collection<int, AttendanceSession> $workerSessions */
            $workerSessions = $sessionsByWorker->get($workerId, new Collection());
            $active = $activeByWorker->get($workerId);
            $status = $this->status($active, $breaks, $localNow);
            $rating = $ratingsByWorker[$workerId] ?? null;
            $averageScore = $rating['average_score'] ?? null;

            $rows[] = [
                'worker_id' => $workerId,
                'worker_name' => $worker->getFullName(),
                'status' => $status,
                'has_current_shift' => $workerShifts->contains(
                    fn(Shift $shift): bool => $attendanceService->matchesCurrentWindow($shift, $now),
                ),
                'shifts' => \array_values($workerShifts->sortBy(
                    fn(Shift $shift): string => $shift->getStartTime(),
                )->map(fn(Shift $shift): array => [
                    'id' => $shift->getKey(),
                    'start_time' => $shift->getStartTimeShort(),
                    'end_time' => $shift->getEndTimeShort(),
                ])->all()),
                'sessions' => \array_values($workerSessions->sortBy(
                    fn(AttendanceSession $session): int => $session->getStartedAt()->getTimestamp(),
                )->map(fn(AttendanceSession $session): array => $this->sessionRow($session, $breaks))->all()),
                'quality' => [
                    'attendance_rating_enabled' => $worker->isAttendanceRatingEnabled(),
                    'average_score' => $averageScore,
                    'evaluated_shifts' => $worker->isAttendanceRatingEnabled()
                        ? ($rating['evaluated_shifts'] ?? 0)
                        : null,
                    'band' => $averageScore === null
                        ? null
                        : ($averageScore >= 90 ? 'good' : ($averageScore >= 70 ? 'warning' : 'poor')),
                ],
            ];
        }

        \usort($rows, function (array $left, array $right): int {
            $leftStart = $left['shifts'][0]['start_time'] ?? null;
            $rightStart = $right['shifts'][0]['start_time'] ?? null;
            if ($leftStart === null && $rightStart !== null) {
                return 1;
            }
            if ($leftStart !== null && $rightStart === null) {
                return -1;
            }
            if ($leftStart !== $rightStart) {
                return ($leftStart ?? '') <=> ($rightStart ?? '');
            }

            return $left['worker_name'] <=> $right['worker_name'];
        });

        $offScheduleWorkers = \array_values($workers
            ->reject(fn(Worker $worker): bool => \in_array($worker->getKey(), $candidateIds, true))
            ->map(fn(Worker $worker): array => [
                'id' => $worker->getKey(),
                'name' => $worker->getFullName(),
            ])
            ->all());

        return [
            'attendance_rows' => $rows,
            'off_schedule_workers' => $offScheduleWorkers,
        ];
    }

    /**
     * @return Collection<int, Worker>
     */
    private function workers(User $owner): Collection
    {
        $query = Worker::query();
        Worker::scopeForUser($query, $owner);
        Worker::querySelect($query);

        return $query->orderBy('last_name')->orderBy('first_name')->get();
    }

    /**
     * @return Collection<int, Shift>
     */
    private function monthShifts(User $owner, Store $store, int $year, int $month): Collection
    {
        $query = Shift::query();
        Shift::scopeForUser($query, $owner);
        Shift::scopeForStore($query, $store->getKey());
        Shift::scopeForMonth($query, $year, $month);
        Shift::querySelect($query);

        return $query->orderBy('date')->orderBy('start_time')->get();
    }

    /**
     * @return Collection<int, AttendanceSession>
     */
    private function activeSessions(User $owner, Store $store): Collection
    {
        $query = AttendanceSession::query();
        AttendanceSession::scopeForUser($query, $owner);
        AttendanceSession::scopeForStore($query, $store->getKey());
        AttendanceSession::querySelect($query);

        return $query->whereNotNull('active_worker_id')->get();
    }

    /**
     * @return Collection<int, AttendanceSession>
     */
    private function todaySessions(
        User $owner,
        Store $store,
        CarbonImmutable $localNow,
    ): Collection {
        $query = AttendanceSession::query();
        AttendanceSession::scopeForUser($query, $owner);
        AttendanceSession::scopeForStore($query, $store->getKey());
        AttendanceSession::querySelect($query);

        return $query
            ->where('started_at', '>=', $localNow->startOfDay()->utc())
            ->where('started_at', '<', $localNow->addDay()->startOfDay()->utc())
            ->orderBy('started_at')
            ->get();
    }

    /**
     * @param Collection<int, AttendanceSession> $sessions
     *
     * @return array<int, list<AttendanceBreak>>
     */
    private function breaks(Collection $sessions): array
    {
        if ($sessions->isEmpty()) {
            return [];
        }
        $query = AttendanceBreak::query();
        AttendanceBreak::querySelect($query);
        $breaks = [];
        foreach ($query->whereIn('attendance_session_id', $sessions->modelKeys())->orderBy('started_at')->get() as $break) {
            $breaks[$break->getAttendanceSessionId()][] = $break;
        }

        return $breaks;
    }

    /**
     * @param array<int, list<AttendanceBreak>> $breaks
     */
    private function status(
        mixed $active,
        array $breaks,
        CarbonImmutable $localNow,
    ): string {
        if (!$active instanceof AttendanceSession) {
            return 'absent';
        }
        if ($active->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toDateString()
            !== $localNow->toDateString()) {
            return 'stale';
        }
        foreach ($breaks[$active->getKey()] ?? [] as $break) {
            if ($break->getEndedAt() === null) {
                return 'break';
            }
        }

        return 'present';
    }

    /**
     * @param array<int, list<AttendanceBreak>> $breaks
     *
     * @return array{
     *     id: int,
     *     started_at: string,
     *     ended_at: string|null,
     *     breaks: list<array{started_at: string, ended_at: string|null}>
     * }
     */
    private function sessionRow(AttendanceSession $session, array $breaks): array
    {
        return [
            'id' => $session->getKey(),
            'started_at' => $session->getStartedAt()
                ->setTimezone(AttendanceService::BUSINESS_TIMEZONE)
                ->toIso8601String(),
            'ended_at' => $session->getEndedAt()
                ?->setTimezone(AttendanceService::BUSINESS_TIMEZONE)
                ->toIso8601String(),
            'breaks' => \array_map(
                fn(AttendanceBreak $break): array => [
                    'started_at' => $break->getStartedAt()
                        ->setTimezone(AttendanceService::BUSINESS_TIMEZONE)
                        ->toIso8601String(),
                    'ended_at' => $break->getEndedAt()
                        ?->setTimezone(AttendanceService::BUSINESS_TIMEZONE)
                        ->toIso8601String(),
                ],
                $breaks[$session->getKey()] ?? [],
            ),
        ];
    }
}
