<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Collection;

class ShiftOverviewService
{
    /**
     * Build the common attendance ratings and monthly worker summary.
     *
     * @param Collection<int, Shift> $shifts
     * @param Collection<int, Worker> $workers
     *
     * @return array{
     *     ratings: array<int, array{
     *         state: string, score: int|null, band: string|null, reason_codes: list<string>,
     *         arrival_offset_minutes: int|null, departure_offset_minutes: int|null,
     *         break_minutes: int|null, break_count: int|null
     *     }>,
     *     monthly_summary: list<array<string, bool|float|int|string|null>>
     * }
     */
    public function build(
        User $owner,
        Store $store,
        Collection $shifts,
        Collection $workers,
        bool $includeSalary,
    ): array {
        $ratingResult = (new AttendanceRatingService())->build($owner, $store, $shifts);
        $ratingsByWorker = [];
        foreach ($ratingResult['summary'] as $row) {
            $ratingsByWorker[$row['worker_id']] = $row;
        }

        $minutesByWorker = [];
        $salaryByWorker = [];
        foreach ($shifts as $shift) {
            $workerId = $shift->getWorkerId();
            $durationMinutes = $shift->getDurationMinutes();
            $minutesByWorker[$workerId] = ($minutesByWorker[$workerId] ?? 0) + $durationMinutes;
            $salaryByWorker[$workerId] = ($salaryByWorker[$workerId] ?? 0)
                + (($durationMinutes / 60) * $shift->getHourlyRate());
        }

        $monthlySummary = [];
        foreach ($workers as $worker) {
            $workerId = $worker->getKey();
            if (!isset($minutesByWorker[$workerId])) {
                continue;
            }

            $rating = $ratingsByWorker[$workerId] ?? null;
            $attendanceRatingEnabled = $worker->isAttendanceRatingEnabled();
            $row = [
                'worker_id' => $workerId,
                'worker_name' => $worker->getFullName(),
                'color' => $worker->getCalendarColor(),
                'hours' => $minutesByWorker[$workerId] / 60,
                'attendance_rating_enabled' => $attendanceRatingEnabled,
                'average_score' => $rating['average_score'] ?? null,
                'evaluated_shifts' => $attendanceRatingEnabled ? ($rating['evaluated_shifts'] ?? 0) : null,
                'good_shifts' => $attendanceRatingEnabled ? ($rating['good_shifts'] ?? 0) : null,
                'late_arrivals' => $attendanceRatingEnabled ? ($rating['late_arrivals'] ?? 0) : null,
                'early_departures' => $attendanceRatingEnabled ? ($rating['early_departures'] ?? 0) : null,
                'break_issues' => $attendanceRatingEnabled ? ($rating['break_issues'] ?? 0) : null,
                'absences' => $attendanceRatingEnabled ? ($rating['absences'] ?? 0) : null,
            ];
            if ($includeSalary) {
                $row['salary'] = \round($salaryByWorker[$workerId] ?? 0, 2);
            }
            $monthlySummary[] = $row;
        }

        return [
            'ratings' => $ratingResult['ratings'],
            'monthly_summary' => $monthlySummary,
        ];
    }
}
