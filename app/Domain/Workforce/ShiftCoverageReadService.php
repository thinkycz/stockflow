<?php

declare(strict_types=1);

namespace App\Domain\Workforce;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Typer;

final class ShiftCoverageReadService
{
    /**
     * @param Collection<int, Shift> $shifts
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function summarize(Collection $shifts, array $filters): array
    {
        $dates = $shifts->map(static fn(Shift $shift): string => $shift->getDate())->unique()->values();
        $requiredStart = Typer::parseNullableString($filters['required_start_time'] ?? null);
        $requiredEnd = Typer::parseNullableString($filters['required_end_time'] ?? null);
        $requiredStartMinutes = $this->timeMinutes($requiredStart);
        $requiredEndMinutes = $this->timeMinutes($requiredEnd);
        $canDetermineCoverage = $requiredStartMinutes !== null && $requiredEndMinutes !== null;
        $range = $this->dateRange($filters, $dates->first(), $dates->last());
        $coverage = [];
        $daysWithoutShifts = [];
        $daysWithoutFullCoverage = [];

        if ($range !== null) {
            [$date, $lastDate] = $range;
            while ($date->lessThanOrEqualTo($lastDate)) {
                $dateString = $date->toDateString();
                $intervals = [];
                foreach ($shifts->filter(static fn(Shift $shift): bool => $dateString === $shift->getDate()) as $shift) {
                    $start = $this->timeMinutes($shift->getStartTime());
                    $end = $this->timeMinutes($shift->getEndTime());
                    if ($start !== null && $end !== null) {
                        $intervals[] = [$start, $end <= $start ? $end + 1440 : $end];
                    }
                }
                $merged = $this->mergeIntervals($intervals);
                $coversRequired = $canDetermineCoverage ? $this->intervalsCover($merged, $requiredStartMinutes, $requiredEndMinutes) : null;
                if ($merged === []) {
                    $daysWithoutShifts[] = $dateString;
                }
                if ($coversRequired === false) {
                    $daysWithoutFullCoverage[] = $dateString;
                }
                $coverage[] = [
                    'date' => $dateString,
                    'scheduled_intervals' => \array_map(fn(array $interval): array => ['start_time' => $this->minutesTime($interval[0]), 'end_time' => $this->minutesTime($interval[1])], $merged),
                    'covers_required_interval' => $coversRequired,
                ];
                $date = $date->copy()->addDay();
            }
        }

        return [
            'shift_count' => $shifts->count(), 'scheduled_days' => $dates->count(),
            'first_shift_date' => $dates->first(), 'last_shift_date' => $dates->last(),
            'total_scheduled_minutes' => $shifts->sum(static fn(Shift $shift): int => $shift->getDurationMinutes()),
            'can_determine_full_coverage' => $canDetermineCoverage, 'required_start_time' => $requiredStart, 'required_end_time' => $requiredEnd,
            'fully_covered' => $canDetermineCoverage ? $daysWithoutFullCoverage === [] : null,
            'days_without_shifts' => $daysWithoutShifts,
            'days_without_full_coverage' => $canDetermineCoverage ? $daysWithoutFullCoverage : null,
            'daily_coverage' => $coverage,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function dateRange(array $filters, mixed $firstShiftDate, mixed $lastShiftDate): array|null
    {
        $year = Typer::parseNullableInt($filters['year'] ?? null);
        $month = Typer::parseNullableInt($filters['month'] ?? null);
        if ($year !== null && $month !== null) { $first = Carbon::parse(\sprintf('%04d-%02d-01', $year, $month))->startOfDay();

            return [$first, $first->copy()->endOfMonth()->startOfDay()]; }
        $first = Typer::parseNullableString($filters['date_from'] ?? null) ?? (\is_string($firstShiftDate) ? $firstShiftDate : null);
        $last = Typer::parseNullableString($filters['date_to'] ?? null) ?? (\is_string($lastShiftDate) ? $lastShiftDate : null);

        return $first === null || $last === null ? null : [Carbon::parse($first)->startOfDay(), Carbon::parse($last)->startOfDay()];
    }

    /**
     * Convert a wall-clock value to minutes from the start of its service day.
     */
    private function timeMinutes(string|null $time): int|null
    {
        return $time !== null && \preg_match('/^(?<hour>[01]\\d|2[0-3]):(?<minute>[0-5]\\d)/', $time, $matches) === 1 ? ((int) $matches['hour'] * 60) + (int) $matches['minute'] : null;
    }

    /**
     * @param list<array{0: int, 1: int}> $intervals
     *
     * @return list<array{0: int, 1: int}>
     */
    private function mergeIntervals(array $intervals): array
    {
        \usort($intervals, static fn(array $left, array $right): int => $left[0] <=> $right[0]);
        $merged = [];
        foreach ($intervals as $interval) {
            $last = \array_key_last($merged);
            if ($last === null || $merged[$last][1] < $interval[0]) {
                $merged[] = $interval;
            } else {
                $merged[$last][1] = \max($merged[$last][1], $interval[1]);
            }
        }

        return $merged;
    }

    /**
     * @param list<array{0: int, 1: int}> $intervals
     */
    private function intervalsCover(array $intervals, int $start, int $end): bool
    {
        $end = $end <= $start ? $end + 1440 : $end;
        foreach ($intervals as $interval) { if ($interval[0] <= $start && $end <= $interval[1]) { return true; } }

        return false;
    }

    /**
     * Format service-day minutes, retaining intervals that end after midnight.
     */
    private function minutesTime(int $minutes): string
    {
        $suffix = $minutes >= 1440 ? '+1d' : '';
        $minutes %= 1440;

        return \sprintf('%02d:%02d%s', \intdiv($minutes, 60), $minutes % 60, $suffix);
    }
}
