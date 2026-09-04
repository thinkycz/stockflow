<?php

declare(strict_types=1);

namespace App\Domain\Workforce;

use App\Models\AttendanceBreak;
use App\Models\AttendanceDeviationReview;
use App\Models\AttendanceSession;
use App\Models\PayrollReport;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @phpstan-type AttendanceReportRow array{id: int, worker_id: int, shift_id: int|null, worker_name: string, worker_color: string, date: string, started_at: string, ended_at: string|null, breaks: list<array{started_at: string, ended_at: string|null, seconds: int}>, break_seconds: int, actual_seconds: int|null, planned_seconds: int|null, difference_seconds: int|null, hourly_rate: float, wage: float|null, stale: bool, voided: bool}
 * @phpstan-type AttendanceSummaryRow array{worker_id: int, worker_name: string, actual_seconds: int, planned_seconds: int, difference_seconds: int, wage: float, incomplete_count: int}
 * @phpstan-type AttendanceDeviationRow array{shift_id: int, primary_session_id: int, status: string, planned_start_time: string, planned_end_time: string, actual_started_at: string, actual_ended_at: string, arrival_offset_seconds: int, departure_offset_seconds: int, can_approve: bool, reason: string|null, reviewed_at: string|null}
 */
class AttendanceReportService
{
    /**
     * Build report rows and per-worker totals for one month.
     *
     * @return array{month: string, rows: list<AttendanceReportRow>, summary: list<AttendanceSummaryRow>, deviations: list<AttendanceDeviationRow>}
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
                'worker_color' => $worker->getCalendarColor(),
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

        return [
            'month' => $month,
            'rows' => $rows,
            'summary' => \array_values($totals),
            'deviations' => $this->deviations($owner, $store, $sessions),
        ];
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

    /**
     * Compare complete attendance boundaries with their current shifts.
     *
     * @param Collection<int, AttendanceSession> $sessions
     *
     * @return list<AttendanceDeviationRow>
     */
    private function deviations(User $owner, Store $store, Collection $sessions): array
    {
        $eligible = $sessions->filter(
            static fn(AttendanceSession $session): bool => $session->getShiftId() !== null && $session->getVoidedAt() === null,
        );
        $shiftIds = $eligible->map(
            static fn(AttendanceSession $session): int => (int) $session->getShiftId(),
        )->unique()->values()->all();
        if ($shiftIds === []) {
            return [];
        }

        $shiftQuery = Shift::query();
        Shift::scopeForUser($shiftQuery, $owner);
        Shift::scopeForStore($shiftQuery, $store->getKey());
        Shift::querySelect($shiftQuery);
        $shifts = $shiftQuery->whereKey($shiftIds)->get()->keyBy(
            static fn(Shift $shift): int => $shift->getKey(),
        );
        $reviewQuery = AttendanceDeviationReview::query();
        AttendanceDeviationReview::scopeForUser($reviewQuery, $owner);
        AttendanceDeviationReview::querySelect($reviewQuery);
        $reviews = $reviewQuery
            ->where('store_id', $store->getKey())
            ->whereIn('shift_id', $shiftIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(static fn(AttendanceDeviationReview $review): int => $review->getShiftId());
        $payrollQuery = PayrollReport::query();
        PayrollReport::scopeForUser($payrollQuery, $owner);
        PayrollReport::querySelect($payrollQuery);
        $closedPayrollPeriods = $payrollQuery
            ->where('store_id', $store->getKey())
            ->where('status', 'closed')
            ->get()
            ->map(static fn(PayrollReport $report): string => \sprintf('%04d-%02d', $report->getYear(), $report->getMonth()))
            ->all();
        $deviations = [];

        foreach ($eligible->groupBy(static fn(AttendanceSession $session): int => (int) $session->getShiftId()) as $shiftId => $shiftSessions) {
            $shift = $shifts->get($shiftId);
            if (!$shift instanceof Shift || $shiftSessions->contains(
                static fn(AttendanceSession $session): bool => $session->getEndedAt() === null,
            )) {
                continue;
            }
            $first = $shiftSessions->sortBy(
                static fn(AttendanceSession $session): int => $session->getStartedAt()->getTimestamp(),
            )->first();
            $last = $shiftSessions->sortByDesc(
                static fn(AttendanceSession $session): int => $session->getEndedAt()?->getTimestamp() ?? 0,
            )->first();
            if (!$first instanceof AttendanceSession || !$last instanceof AttendanceSession || $last->getEndedAt() === null) {
                continue;
            }
            $plannedStart = CarbonImmutable::parse(
                $shift->getDate() . ' ' . $shift->getStartTime(),
                AttendanceService::BUSINESS_TIMEZONE,
            );
            $plannedEnd = CarbonImmutable::parse(
                $shift->getDate() . ' ' . $shift->getEndTime(),
                AttendanceService::BUSINESS_TIMEZONE,
            );
            $arrivalOffset = $first->getStartedAt()->getTimestamp() - $plannedStart->utc()->getTimestamp();
            $departureOffset = $last->getEndedAt()->getTimestamp() - $plannedEnd->utc()->getTimestamp();
            $matchingReview = $reviews->get($shift->getKey(), new Collection())->first(
                static fn(AttendanceDeviationReview $review): bool => $review->getActualStartedAt()->getTimestamp() === $first->getStartedAt()->getTimestamp() &&
                    $review->getActualEndedAt()->getTimestamp() === $last->getEndedAt()->getTimestamp() &&
                    \mb_substr($review->getAfterStartTime(), 0, 5) === $shift->getStartTimeShort() &&
                    \mb_substr($review->getAfterEndTime(), 0, 5) === $shift->getEndTimeShort(),
            );
            if (\abs($arrivalOffset) <= 900 && \abs($departureOffset) <= 900 && !($matchingReview instanceof AttendanceDeviationReview)) {
                continue;
            }
            $deviations[] = [
                'shift_id' => $shift->getKey(),
                'primary_session_id' => $first->getKey(),
                'status' => $matchingReview?->getDecision()->value ?? 'pending',
                'planned_start_time' => $shift->getStartTimeShort(),
                'planned_end_time' => $shift->getEndTimeShort(),
                'actual_started_at' => $first->getStartedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toIso8601String(),
                'actual_ended_at' => $last->getEndedAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toIso8601String(),
                'arrival_offset_seconds' => $arrivalOffset,
                'departure_offset_seconds' => $departureOffset,
                'can_approve' => !\in_array(\mb_substr($shift->getDate(), 0, 7), $closedPayrollPeriods, true),
                'reason' => $matchingReview?->getReason(),
                'reviewed_at' => $matchingReview?->getCreatedAt()->toIso8601String(),
            ];
        }

        return $deviations;
    }
}
