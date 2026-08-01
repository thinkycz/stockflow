<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AttendanceBreak;
use App\Models\AttendanceSession;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;

/**
 * @phpstan-type AttendanceReportRow array{id: int, worker_id: int, shift_id: int|null, worker_name: string, date: string, started_at: string, ended_at: string|null, breaks: list<array{started_at: string, ended_at: string|null, seconds: int}>, break_seconds: int, actual_seconds: int|null, planned_seconds: int|null, difference_seconds: int|null, hourly_rate: float, wage: float|null, stale: bool, voided: bool}
 * @phpstan-type AttendanceSummaryRow array{worker_id: int, worker_name: string, actual_seconds: int, planned_seconds: int, difference_seconds: int, wage: float, incomplete_count: int}
 */
class AttendanceReportService
{
    /**
     * Build report rows and per-worker totals for one month.
     *
     * @return array{month: string, rows: list<AttendanceReportRow>, summary: list<AttendanceSummaryRow>}
     */
    public function build(User $owner, Store $store, string $month, int|null $workerId): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, AttendanceService::BUSINESS_TIMEZONE);
        if (!$start instanceof CarbonImmutable) {
            $start = CarbonImmutable::now(AttendanceService::BUSINESS_TIMEZONE)->startOfMonth();
            $month = $start->format('Y-m');
        }
        $end = $start->addMonth();
        $periodStart = $start->utc();
        $periodEnd = $end->utc();
        $query = AttendanceSession::query();
        AttendanceSession::scopeForUser($query, $owner);
        AttendanceSession::scopeForStore($query, $store->getKey());
        if ($workerId !== null) {
            $query->where('worker_id', $workerId);
        }
        AttendanceSession::querySelect($query);
        $sessions = $query
            ->where('started_at', '<', $periodEnd)
            ->where(static function ($query) use ($periodStart): void {
                $query->whereNull('ended_at')->orWhere('ended_at', '>=', $periodStart);
            })
            ->orderBy('started_at')
            ->get();

        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $owner);
        Worker::querySelect($workerQuery);
        $workers = $workerQuery->get()->keyBy(static fn(Worker $worker): int => $worker->getKey());
        $rows = [];
        $totals = [];

        foreach ($sessions as $session) {
            $worker = $workers->get($session->getWorkerId());
            if (!$worker instanceof Worker) {
                continue;
            }
            $breakRows = [];
            $breakSeconds = 0;
            $endedAt = $session->getEndedAt();
            $effectiveStart = $session->getStartedAt()->greaterThan($periodStart) ? $session->getStartedAt() : $periodStart;
            $effectiveEnd = $endedAt !== null && $endedAt->lessThan($periodEnd) ? $endedAt : $periodEnd;
            foreach ($session->attendanceBreaks()->orderBy('started_at')->get() as $attendanceBreak) {
                $breakEnd = $attendanceBreak->getEndedAt();
                $breakStartInPeriod = $attendanceBreak->getStartedAt()->greaterThan($effectiveStart) ? $attendanceBreak->getStartedAt() : $effectiveStart;
                $breakEndInPeriod = $breakEnd !== null && $breakEnd->lessThan($effectiveEnd) ? $breakEnd : $effectiveEnd;
                $seconds = $breakEnd === null || $breakEndInPeriod->lessThanOrEqualTo($breakStartInPeriod)
                    ? 0 : (int) $breakStartInPeriod->diffInSeconds($breakEndInPeriod);
                $breakSeconds += $seconds;
                $breakRows[] = [
                    'started_at' => $attendanceBreak->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toIso8601String(),
                    'ended_at' => $breakEnd?->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toIso8601String(),
                    'seconds' => $seconds,
                ];
            }
            $stale = $endedAt === null && $session->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toDateString()
                !== CarbonImmutable::now(AttendanceService::BUSINESS_TIMEZONE)->toDateString();
            $voided = $session->getVoidedAt() !== null;
            $actualSeconds = $endedAt === null || $voided ? null : \max(0, (int) $effectiveStart->diffInSeconds($effectiveEnd) - $breakSeconds);
            $scheduledStart = $session->getScheduledStartTime();
            $scheduledEnd = $session->getScheduledEndTime();
            $scheduledDate = $session->getScheduledDate();
            $plannedSeconds = $scheduledStart === null || $scheduledEnd === null || $scheduledDate === null ||
                $scheduledDate->lessThan($start) || !$scheduledDate->lessThan($end)
                ? null
                : (int) CarbonImmutable::parse($scheduledStart)->diffInSeconds(CarbonImmutable::parse($scheduledEnd));
            $difference = $actualSeconds === null || $plannedSeconds === null ? null : $actualSeconds - $plannedSeconds;
            $wage = $actualSeconds === null ? null : \round(($actualSeconds / 3600) * $session->getHourlyRate(), 2);
            $rows[] = [
                'id' => $session->getKey(),
                'worker_id' => $worker->getKey(),
                'shift_id' => $session->getShiftId(),
                'worker_name' => $worker->getFullName(),
                'date' => $session->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toDateString(),
                'started_at' => $session->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toIso8601String(),
                'ended_at' => $endedAt?->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toIso8601String(),
                'breaks' => $breakRows,
                'break_seconds' => $breakSeconds,
                'actual_seconds' => $actualSeconds,
                'planned_seconds' => $plannedSeconds,
                'difference_seconds' => $difference,
                'hourly_rate' => $session->getHourlyRate(),
                'wage' => $wage,
                'stale' => $stale,
                'voided' => $voided,
            ];
            $total = $totals[$worker->getKey()] ?? [
                'worker_id' => $worker->getKey(), 'worker_name' => $worker->getFullName(),
                'actual_seconds' => 0, 'planned_seconds' => 0, 'difference_seconds' => 0,
                'wage' => 0.0, 'incomplete_count' => 0,
            ];
            if ($actualSeconds === null) {
                ++$total['incomplete_count'];
            } else {
                $total['actual_seconds'] += $actualSeconds;
                $total['planned_seconds'] += $plannedSeconds ?? 0;
                $total['difference_seconds'] += $difference ?? 0;
                $total['wage'] = \round($total['wage'] + ($wage ?? 0), 2);
            }
            $totals[$worker->getKey()] = $total;
        }

        return ['month' => $month, 'rows' => $rows, 'summary' => \array_values($totals)];
    }

    /**
     * Derive the current aggregate staffing state of a store.
     */
    public function storeState(User $owner, Store $store): string
    {
        $query = AttendanceSession::query();
        AttendanceSession::scopeForUser($query, $owner);
        AttendanceSession::scopeForStore($query, $store->getKey());
        AttendanceSession::querySelect($query);
        $active = $query->whereNotNull('active_worker_id')->get();
        $today = CarbonImmutable::now(AttendanceService::BUSINESS_TIMEZONE)->toDateString();

        foreach ($active as $session) {
            if ($today !== $session->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toDateString()) {
                return 'unclear';
            }
        }
        foreach ($active as $session) {
            if (!AttendanceBreak::query()->where('active_session_id', $session->getKey())->exists()) {
                return 'occupied';
            }
        }

        return 'empty';
    }
}
