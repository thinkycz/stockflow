<?php

declare(strict_types=1);

namespace App\Domain\Payroll;

use App\Domain\Workforce\AttendanceReportService;
use App\Enums\PayrollAdjustmentTypeEnum;
use App\Enums\PayrollReportStatusEnum;
use App\Models\PayrollAdjustment;
use App\Models\PayrollReport;
use App\Models\PayrollWageOverride;
use App\Models\PayrollWorkerEntry;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
use Thinkycz\LaravelCore\Support\Typer;

class PayrollReportReadService
{
    /**
     * Build the payroll report for one store and month.
     *
     * @return array<string, mixed>
     */
    public function build(User $admin, Store $store, int $year, int $month): array
    {
        return $this->presentReport($this->buildExact($admin, $store, $year, $month));
    }

    /**
     * Return a worker's exact payslip for transactional mutation validation.
     *
     * @return array<string, mixed>
     */
    public function buildExactPayslip(User $admin, Store $store, int $year, int $month, int $workerId): array
    {
        return $this->payslipForWorker($this->buildExact($admin, $store, $year, $month), $workerId);
    }

    /**
     * Build one worker's payslip in a store and month.
     *
     * @return array<string, mixed>|null
     */
    public function buildDetail(User $admin, Store $store, int $year, int $month, int $workerId): array|null
    {
        $report = $this->build($admin, $store, $year, $month);
        $payslip = $this->payslipForWorker($report, $workerId);

        return $payslip === [] ? null : [
            'report' => $report,
            'payslip' => $payslip,
        ];
    }

    /**
     * Build a payroll report whose monetary values remain exact decimals.
     *
     * @return array<string, mixed>
     */
    public function buildExact(User $admin, Store $store, int $year, int $month): array
    {
        $this->assertStore($admin, $store);
        $report = $this->findReport($admin, $store, $year, $month);
        if ($report?->isClosed() === true && $report->getSnapshot() !== null) {
            return \array_merge($report->getSnapshot(), $this->reportMeta($report));
        }

        $shiftQuery = Shift::query();
        Shift::scopeForUser($shiftQuery, $admin);
        Shift::scopeForStore($shiftQuery, $store->getKey());
        Shift::scopeForMonth($shiftQuery, $year, $month);
        Shift::querySelect($shiftQuery);
        $shifts = $shiftQuery->orderBy('date')->orderBy('start_time')->get();
        $adjustments = $report instanceof PayrollReport ? $report->getAdjustments() : new Collection();
        $wageOverrides = $report instanceof PayrollReport ? $report->getWageOverrides() : new Collection();
        $workerEntries = $report instanceof PayrollReport ? $report->getWorkerEntries() : new Collection();
        $attendanceRows = (new AttendanceReportService())->build(
            $admin,
            $store,
            \sprintf('%04d-%02d', $year, $month),
            null,
        )['rows'];
        $workerIds = \array_values(\array_unique([
            ...$shifts->map(static fn(Shift $shift): int => $shift->getWorkerId())->all(),
            ...$adjustments->map(static fn(PayrollAdjustment $adjustment): int => $adjustment->getWorkerId())->all(),
            ...$wageOverrides->map(static fn(PayrollWageOverride $override): int => $override->getWorkerId())->all(),
            ...$workerEntries->map(static fn(PayrollWorkerEntry $entry): int => $entry->getWorkerId())->all(),
            ...\array_map(static fn(array $row): int => $row['worker_id'], $attendanceRows),
        ]));

        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $admin);
        Worker::querySelect($workerQuery);
        $workers = $workerQuery->whereKey($workerIds)->get()->keyBy(static fn(Worker $worker): int => $worker->getKey());
        $payslips = [];

        foreach ($workers->sortBy(static fn(Worker $worker): string => $worker->getLastName() . $worker->getFirstName()) as $worker) {
            $workerShifts = $shifts->where('worker_id', $worker->getKey());
            $workerAdjustments = $adjustments->where('worker_id', $worker->getKey());
            $wageOverride = $wageOverrides->first(
                static fn(PayrollWageOverride $override): bool => $override->getWorkerId() === $worker->getKey(),
            );
            $manuallyAdded = $workerEntries->contains(
                static fn(PayrollWorkerEntry $entry): bool => $entry->getWorkerId() === $worker->getKey(),
            );
            $workerAttendance = \array_values(\array_filter(
                $attendanceRows,
                static fn(array $row): bool => $row['worker_id'] === $worker->getKey(),
            ));
            $shiftRows = [];
            $plannedMinutes = 0;
            $baseAmount = Money::zero();
            foreach ($workerShifts as $shift) {
                $minutes = \max(0, $shift->getDurationMinutes());
                $hourlyRate = Money::of($shift->getHourlyRateDecimal());
                $amount = $hourlyRate->multipliedBy($minutes)->dividedBy(60, 2, RoundingMode::HalfUp);
                $matchedAttendance = \array_values(\array_filter(
                    $workerAttendance,
                    static fn(array $row): bool => $row['shift_id'] === $shift->getKey() && $row['voided'] === false,
                ));
                $actualSeconds = 0;
                $incomplete = false;
                foreach ($matchedAttendance as $attendanceRow) {
                    if ($attendanceRow['actual_seconds'] === null) {
                        $incomplete = true;
                    } else {
                        $actualSeconds += $attendanceRow['actual_seconds'];
                    }
                }
                $plannedMinutes += $minutes;
                $baseAmount = $baseAmount->plus($amount);
                $shiftRows[] = [
                    'id' => $shift->getKey(),
                    'date' => $shift->getDate(),
                    'start_time' => $shift->getStartTimeShort(),
                    'end_time' => $shift->getEndTimeShort(),
                    'planned_minutes' => $minutes,
                    'hourly_rate' => $hourlyRate,
                    'amount' => $amount,
                    'actual_seconds' => $actualSeconds,
                    'difference_seconds' => $incomplete ? null : $actualSeconds - ($minutes * 60),
                    'attendance_incomplete' => $incomplete,
                ];
            }

            $automaticBaseAmount = $baseAmount;
            $automaticHours = BigDecimal::of($plannedMinutes)->dividedBy(60, 2, RoundingMode::HalfUp);
            $automaticHourlyRate = $automaticHours->isPositive()
                ? $automaticBaseAmount->dividedBy($automaticHours, 2, RoundingMode::HalfUp)
                : ($manuallyAdded ? Money::of($worker->getHourlyRateDecimal()) : Money::zero());
            if ($wageOverride instanceof PayrollWageOverride) {
                $baseAmount = BigDecimal::of($wageOverride->getHoursDecimal())
                    ->multipliedBy(Money::of($wageOverride->getHourlyRateDecimal()))
                    ->toScale(2, RoundingMode::HalfUp);
            }

            $tipAmount = Money::zero();
            $deductionAmount = Money::zero();
            $adjustmentRows = [];
            foreach ($workerAdjustments as $adjustment) {
                if ($adjustment->getType() === PayrollAdjustmentTypeEnum::TIP) {
                    $tipAmount = $tipAmount->plus(Money::of($adjustment->getAmountDecimal()));
                } else {
                    $deductionAmount = $deductionAmount->plus(Money::of($adjustment->getAmountDecimal()));
                }
                $adjustmentRows[] = [
                    'id' => $adjustment->getKey(),
                    'type' => $adjustment->getType()->value,
                    'amount' => Money::of($adjustment->getAmountDecimal()),
                    'reason' => $adjustment->getReason(),
                ];
            }

            $actualSeconds = 0;
            $incompleteCount = 0;
            $unmatchedCount = 0;
            foreach ($workerAttendance as $attendanceRow) {
                if ($attendanceRow['voided'] === true) {
                    continue;
                }
                if ($attendanceRow['actual_seconds'] === null) {
                    ++$incompleteCount;
                } else {
                    $actualSeconds += $attendanceRow['actual_seconds'];
                }
                if ($attendanceRow['shift_id'] === null) {
                    ++$unmatchedCount;
                }
            }

            $payslips[] = [
                'worker_id' => $worker->getKey(),
                'worker_name' => $worker->getFullName(),
                'planned_minutes' => $plannedMinutes,
                'actual_seconds' => $actualSeconds,
                'automatic_base_amount' => $automaticBaseAmount,
                'payable_hours' => $wageOverride instanceof PayrollWageOverride ? BigDecimal::of($wageOverride->getHoursDecimal())->toScale(2) : $automaticHours,
                'payable_hourly_rate' => $wageOverride instanceof PayrollWageOverride ? Money::of($wageOverride->getHourlyRateDecimal()) : $automaticHourlyRate,
                'wage_overridden' => $wageOverride instanceof PayrollWageOverride,
                'manually_added' => $manuallyAdded,
                'can_remove' => $manuallyAdded &&
                    $workerShifts->isEmpty() &&
                    $workerAttendance === [] &&
                    $workerAdjustments->isEmpty() &&
                    !($wageOverride instanceof PayrollWageOverride),
                'base_amount' => $baseAmount,
                'tip_amount' => $tipAmount,
                'deduction_amount' => $deductionAmount,
                'final_amount' => $baseAmount->plus($tipAmount)->minus($deductionAmount),
                'incomplete_count' => $incompleteCount,
                'unmatched_count' => $unmatchedCount,
                'shifts' => $shiftRows,
                'attendance' => $workerAttendance,
                'adjustments' => $adjustmentRows,
            ];
        }

        return \array_merge([
            'year' => $year,
            'month' => $month,
            'payslips' => $payslips,
        ], $this->reportMeta($report));
    }

    /**
     * Find a payslip row by worker id.
     *
     * @param array<string, mixed> $report
     *
     * @return array<string, mixed>
     */
    private function payslipForWorker(array $report, int $workerId): array
    {
        foreach (Typer::assertArray($report['payslips'] ?? null) as $value) {
            $payslip = Typer::assertStringKeyArray(Typer::assertArray($value));
            if ($workerId === Typer::assertInt($payslip['worker_id'] ?? null)) {
                return $payslip;
            }
        }

        return [];
    }

    /**
     * Find a payroll report.
     */
    private function findReport(User $admin, Store $store, int $year, int $month): PayrollReport|null
    {
        $query = PayrollReport::query();
        PayrollReport::scopeForUser($query, $admin);

        return $query
            ->where('store_id', $store->getKey())
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * Convert exact report decimals only when building the legacy presentation payload.
     *
     * @param array<string, mixed> $report
     *
     * @return array<string, mixed>
     */
    private function presentReport(array $report): array
    {
        return Typer::assertStringKeyArray(Typer::assertArray($this->presentValue($report)));
    }

    /**
     * Recursively convert exact decimals at the presentation boundary.
     */
    private function presentValue(mixed $value): mixed
    {
        if ($value instanceof BigDecimal) {
            return Money::present($value);
        }

        if (\is_array($value)) {
            $presented = [];
            foreach ($value as $key => $item) {
                $presented[$key] = $this->presentValue($item);
            }

            return $presented;
        }

        return $value;
    }

    /**
     * Add lifecycle metadata to a payload.
     *
     * @return array<string, mixed>
     */
    private function reportMeta(PayrollReport|null $report): array
    {
        return [
            'id' => $report?->getKey(),
            'status' => $report?->getStatus()->value ?? PayrollReportStatusEnum::OPEN->value,
            'closed_at' => $report?->getClosedAt()?->toIso8601String(),
            'reopened_at' => $report?->getReopenedAt()?->toIso8601String(),
        ];
    }

    /**
     * Enforce owning-admin and retail-store scope.
     */
    private function assertStore(User $admin, Store $store): void
    {
        if (!$admin->isAdmin() || $store->getUserId() !== $admin->getKey() || $store->isWarehouse()) {
            \abort(404);
        }
    }
}
