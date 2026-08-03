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

class AttendanceRatingService
{
    public const int GRACE_SECONDS = 300;

    public const int MAX_BREAK_SECONDS = 1800;

    /**
     * Build attendance ratings and per-worker aggregates for the supplied shifts.
     *
     * @param Collection<int, Shift> $shifts
     *
     * @return array{
     *     ratings: array<int, array{
     *         state: string, score: int|null, band: string|null, reason_codes: list<string>,
     *         arrival_offset_minutes: int|null, departure_offset_minutes: int|null,
     *         break_minutes: int|null, break_count: int|null
     *     }>,
     *     summary: list<array{
     *         worker_id: int, attendance_rating_enabled: bool, average_score: int|null,
     *         evaluated_shifts: int|null, good_shifts: int|null, late_arrivals: int|null,
     *         early_departures: int|null, break_issues: int|null, absences: int|null
     *     }>
     * }
     */
    public function build(User $owner, Store $store, Collection $shifts): array
    {
        $ratingEnabledByWorker = $this->ratingEnabledByWorker($owner, $shifts);
        $ratedShifts = $shifts->filter(
            static fn(Shift $shift): bool => $ratingEnabledByWorker[$shift->getWorkerId()] ?? false,
        );
        $sessions = $this->sessions($owner, $store, $ratedShifts);
        $breaks = $this->breaks($sessions);
        $sessionsByShift = $sessions->groupBy(
            static fn(AttendanceSession $session): int => (int) $session->getShiftId(),
        );
        $ratings = [];
        $summary = [];
        $disabledWorkerIds = [];

        foreach ($shifts as $shift) {
            $workerId = $shift->getWorkerId();
            if (!($ratingEnabledByWorker[$workerId] ?? false)) {
                $ratings[$shift->getKey()] = $this->disabled();
                $disabledWorkerIds[$workerId] = true;

                continue;
            }
            $summary[$workerId] ??= [
                'worker_id' => $workerId,
                'attendance_rating_enabled' => true,
                'score_total' => 0,
                'average_score' => null,
                'evaluated_shifts' => 0,
                'good_shifts' => 0,
                'late_arrivals' => 0,
                'early_departures' => 0,
                'break_issues' => 0,
                'absences' => 0,
            ];
            $rating = $this->rateShift(
                $shift,
                $sessionsByShift->get($shift->getKey(), new Collection()),
                $breaks,
            );
            $ratings[$shift->getKey()] = $rating;

            if ($rating['state'] !== 'scored' || $rating['score'] === null) {
                continue;
            }

            ++$summary[$workerId]['evaluated_shifts'];
            $summary[$workerId]['score_total'] += $rating['score'];
            if ($rating['score'] >= 90) {
                ++$summary[$workerId]['good_shifts'];
            }
            if (\in_array('late_arrival', $rating['reason_codes'], true)) {
                ++$summary[$workerId]['late_arrivals'];
            }
            if (\in_array('early_departure', $rating['reason_codes'], true)) {
                ++$summary[$workerId]['early_departures'];
            }
            if (\in_array('excessive_break_duration', $rating['reason_codes'], true) ||
                \in_array('excessive_break_count', $rating['reason_codes'], true)) {
                ++$summary[$workerId]['break_issues'];
            }
            if (\in_array('absence', $rating['reason_codes'], true)) {
                ++$summary[$workerId]['absences'];
            }
        }

        $summaryRows = [];
        foreach ($summary as $row) {
            $summaryRows[$row['worker_id']] = [
                'worker_id' => $row['worker_id'],
                'attendance_rating_enabled' => $row['attendance_rating_enabled'],
                'average_score' => $row['evaluated_shifts'] > 0
                    ? (int) \round($row['score_total'] / $row['evaluated_shifts'])
                    : null,
                'evaluated_shifts' => $row['evaluated_shifts'],
                'good_shifts' => $row['good_shifts'],
                'late_arrivals' => $row['late_arrivals'],
                'early_departures' => $row['early_departures'],
                'break_issues' => $row['break_issues'],
                'absences' => $row['absences'],
            ];
        }
        foreach (\array_keys($disabledWorkerIds) as $workerId) {
            $summaryRows[$workerId] = [
                'worker_id' => $workerId,
                'attendance_rating_enabled' => false,
                'average_score' => null,
                'evaluated_shifts' => null,
                'good_shifts' => null,
                'late_arrivals' => null,
                'early_departures' => null,
                'break_issues' => null,
                'absences' => null,
            ];
        }
        \ksort($summaryRows);

        return ['ratings' => $ratings, 'summary' => \array_values($summaryRows)];
    }

    /**
     * Resolve attendance rating settings for all workers referenced by the shifts.
     *
     * @param Collection<int, Shift> $shifts
     *
     * @return array<int, bool>
     */
    private function ratingEnabledByWorker(User $owner, Collection $shifts): array
    {
        if ($shifts->isEmpty()) {
            return [];
        }

        $query = Worker::query();
        Worker::scopeForUser($query, $owner);
        Worker::querySelect($query);
        $enabled = [];

        foreach ($query->whereIn('id', $shifts->pluck('worker_id')->unique()->values())->get() as $worker) {
            $enabled[$worker->getKey()] = $worker->isAttendanceRatingEnabled();
        }

        return $enabled;
    }

    /**
     * Load non-voided attendance sessions for all supplied shifts in one query.
     *
     * @param Collection<int, Shift> $shifts
     *
     * @return Collection<int, AttendanceSession>
     */
    private function sessions(User $owner, Store $store, Collection $shifts): Collection
    {
        if ($shifts->isEmpty()) {
            return new Collection();
        }

        $query = AttendanceSession::query();
        AttendanceSession::scopeForUser($query, $owner);
        AttendanceSession::scopeForStore($query, $store->getKey());
        AttendanceSession::querySelect($query);

        return $query
            ->whereIn('shift_id', $shifts->modelKeys())
            ->whereNull('voided_at')
            ->orderBy('started_at')
            ->get();
    }

    /**
     * Load all breaks for the supplied sessions and group them by session id.
     *
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
        $grouped = [];
        foreach ($query->whereIn('attendance_session_id', $sessions->modelKeys())->orderBy('started_at')->get() as $break) {
            $grouped[$break->getAttendanceSessionId()][] = $break;
        }

        return $grouped;
    }

    /**
     * Calculate one shift rating from its complete non-voided attendance timeline.
     *
     * @param Collection<int, AttendanceSession> $sessions
     * @param array<int, list<AttendanceBreak>> $breaks
     *
     * @return array{
     *     state: string, score: int|null, band: string|null, reason_codes: list<string>,
     *     arrival_offset_minutes: int|null, departure_offset_minutes: int|null,
     *     break_minutes: int, break_count: int
     * }
     */
    private function rateShift(Shift $shift, Collection $sessions, array $breaks): array
    {
        $firstSession = $sessions->first();
        $scheduledDate = $firstSession instanceof AttendanceSession && $firstSession->getScheduledDate() !== null
            ? $firstSession->getScheduledDate()->format('Y-m-d')
            : $shift->getDate();
        $scheduledStartTime = $firstSession instanceof AttendanceSession
            ? ($firstSession->getScheduledStartTime() ?? $shift->getStartTime())
            : $shift->getStartTime();
        $scheduledEndTime = $firstSession instanceof AttendanceSession
            ? ($firstSession->getScheduledEndTime() ?? $shift->getEndTime())
            : $shift->getEndTime();
        $scheduledStart = CarbonImmutable::parse(
            $scheduledDate . ' ' . $scheduledStartTime,
            AttendanceService::BUSINESS_TIMEZONE,
        );
        $scheduledEnd = CarbonImmutable::parse(
            $scheduledDate . ' ' . $scheduledEndTime,
            AttendanceService::BUSINESS_TIMEZONE,
        );

        foreach ($sessions as $session) {
            if ($session->getEndedAt() === null) {
                return $this->unscored('pending');
            }
        }
        if (CarbonImmutable::now('UTC')->lessThan($scheduledEnd->utc())) {
            return $this->unscored('future');
        }
        if ($sessions->isEmpty()) {
            return [
                'state' => 'scored', 'score' => 0, 'band' => 'poor', 'reason_codes' => ['absence'],
                'arrival_offset_minutes' => null, 'departure_offset_minutes' => null,
                'break_minutes' => 0, 'break_count' => 0,
            ];
        }

        $arrival = $sessions->first()->getStartedAt();
        $departure = $sessions->last()->getEndedAt();
        if ($departure === null) {
            return $this->unscored('pending');
        }

        $breakSeconds = 0;
        $breakCount = 0;
        $previousEnd = null;
        foreach ($sessions as $session) {
            if ($previousEnd !== null && $session->getStartedAt()->greaterThan($previousEnd)) {
                $breakSeconds += (int) $previousEnd->diffInSeconds($session->getStartedAt());
                ++$breakCount;
            }
            foreach ($breaks[$session->getKey()] ?? [] as $break) {
                $breakEnd = $break->getEndedAt();
                if ($breakEnd === null) {
                    return $this->unscored('pending');
                }
                $breakSeconds += (int) $break->getStartedAt()->diffInSeconds($breakEnd);
                ++$breakCount;
            }
            $previousEnd = $session->getEndedAt();
        }

        $arrivalOffsetSeconds = $arrival->getTimestamp() - $scheduledStart->utc()->getTimestamp();
        $departureOffsetSeconds = $departure->getTimestamp() - $scheduledEnd->utc()->getTimestamp();
        $plannedSeconds = $scheduledEnd->getTimestamp() - $scheduledStart->getTimestamp();
        $allowedBreakSeconds = \min(self::MAX_BREAK_SECONDS, (int) \floor($plannedSeconds * 0.1));
        $reasons = [];
        $score = 100;

        if ($arrivalOffsetSeconds > self::GRACE_SECONDS) {
            $reasons[] = 'late_arrival';
            $score -= \min(35, $this->ceilMinutes($arrivalOffsetSeconds - self::GRACE_SECONDS) * 2);
        }
        if ($departureOffsetSeconds < -self::GRACE_SECONDS) {
            $reasons[] = 'early_departure';
            $score -= \min(35, $this->ceilMinutes(-$departureOffsetSeconds - self::GRACE_SECONDS) * 2);
        }
        if ($breakSeconds > $allowedBreakSeconds) {
            $reasons[] = 'excessive_break_duration';
            $score -= \min(20, $this->ceilMinutes($breakSeconds - $allowedBreakSeconds));
        }
        if ($breakCount > 2) {
            $reasons[] = 'excessive_break_count';
            $score -= \min(10, ($breakCount - 2) * 5);
        }
        $score = \max(0, $score);

        return [
            'state' => 'scored',
            'score' => $score,
            'band' => $score >= 90 ? 'good' : ($score >= 70 ? 'warning' : 'poor'),
            'reason_codes' => $reasons,
            'arrival_offset_minutes' => $this->signedMinutes($arrivalOffsetSeconds),
            'departure_offset_minutes' => $this->signedMinutes($departureOffsetSeconds),
            'break_minutes' => $this->ceilMinutes($breakSeconds),
            'break_count' => $breakCount,
        ];
    }

    /**
     * Return the common shape for a shift that cannot be scored yet.
     *
     * @return array{
     *     state: string, score: null, band: null, reason_codes: list<string>,
     *     arrival_offset_minutes: null, departure_offset_minutes: null,
     *     break_minutes: int, break_count: int
     * }
     */
    private function unscored(string $state): array
    {
        return [
            'state' => $state, 'score' => null, 'band' => null, 'reason_codes' => [],
            'arrival_offset_minutes' => null, 'departure_offset_minutes' => null,
            'break_minutes' => 0, 'break_count' => 0,
        ];
    }

    /**
     * Return the rating shape for a worker who opted out of attendance rating.
     *
     * @return array{
     *     state: string, score: null, band: null, reason_codes: list<string>,
     *     arrival_offset_minutes: null, departure_offset_minutes: null,
     *     break_minutes: null, break_count: null
     * }
     */
    private function disabled(): array
    {
        return [
            'state' => 'disabled', 'score' => null, 'band' => null, 'reason_codes' => [],
            'arrival_offset_minutes' => null, 'departure_offset_minutes' => null,
            'break_minutes' => null, 'break_count' => null,
        ];
    }

    /**
     * Round a positive number of seconds up to whole minutes.
     */
    private function ceilMinutes(int $seconds): int
    {
        return (int) \ceil($seconds / 60);
    }

    /**
     * Round a signed offset away from zero to whole minutes.
     */
    private function signedMinutes(int $seconds): int
    {
        return $seconds < 0 ? -$this->ceilMinutes(-$seconds) : $this->ceilMinutes($seconds);
    }
}
