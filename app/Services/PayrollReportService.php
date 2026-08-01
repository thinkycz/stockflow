<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PayrollAdjustmentTypeEnum;
use App\Enums\PayrollReportStatusEnum;
use App\Models\FinancialReport;
use App\Models\PayrollAdjustment;
use App\Models\PayrollReport;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class PayrollReportService
{
    /**
     * Build the payroll report for one store and month.
     *
     * @return array<string, mixed>
     */
    public function build(User $admin, Store $store, int $year, int $month): array
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
        $attendanceRows = (new AttendanceReportService())->build(
            $admin,
            $store,
            \sprintf('%04d-%02d', $year, $month),
            null,
        )['rows'];
        $workerIds = \array_values(\array_unique([
            ...$shifts->map(static fn(Shift $shift): int => $shift->getWorkerId())->all(),
            ...$adjustments->map(static fn(PayrollAdjustment $adjustment): int => $adjustment->getWorkerId())->all(),
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
            $workerAttendance = \array_values(\array_filter(
                $attendanceRows,
                static fn(array $row): bool => $row['worker_id'] === $worker->getKey(),
            ));
            $shiftRows = [];
            $plannedMinutes = 0;
            $baseAmount = 0.0;
            foreach ($workerShifts as $shift) {
                $minutes = \max(0, $shift->getDurationMinutes());
                $amount = \round(($minutes / 60) * $shift->getHourlyRate(), 2);
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
                $baseAmount = \round($baseAmount + $amount, 2);
                $shiftRows[] = [
                    'id' => $shift->getKey(),
                    'date' => $shift->getDate(),
                    'start_time' => $shift->getStartTimeShort(),
                    'end_time' => $shift->getEndTimeShort(),
                    'planned_minutes' => $minutes,
                    'hourly_rate' => $shift->getHourlyRate(),
                    'amount' => $amount,
                    'actual_seconds' => $actualSeconds,
                    'difference_seconds' => $incomplete ? null : $actualSeconds - ($minutes * 60),
                    'attendance_incomplete' => $incomplete,
                ];
            }

            $tipAmount = 0.0;
            $deductionAmount = 0.0;
            $adjustmentRows = [];
            foreach ($workerAdjustments as $adjustment) {
                if ($adjustment->getType() === PayrollAdjustmentTypeEnum::TIP) {
                    $tipAmount = \round($tipAmount + $adjustment->getAmount(), 2);
                } else {
                    $deductionAmount = \round($deductionAmount + $adjustment->getAmount(), 2);
                }
                $adjustmentRows[] = [
                    'id' => $adjustment->getKey(),
                    'type' => $adjustment->getType()->value,
                    'amount' => $adjustment->getAmount(),
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
                'base_amount' => $baseAmount,
                'tip_amount' => $tipAmount,
                'deduction_amount' => $deductionAmount,
                'final_amount' => \round($baseAmount + $tipAmount - $deductionAmount, 2),
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
     * Create an adjustment on an open payroll report.
     */
    public function createAdjustment(
        User $admin,
        Store $store,
        int $year,
        int $month,
        Worker $worker,
        PayrollAdjustmentTypeEnum $type,
        float $amount,
        string $reason,
    ): PayrollAdjustment {
        return DB::transaction(function () use ($admin, $store, $year, $month, $worker, $type, $amount, $reason): PayrollAdjustment {
            $report = $this->openReport($admin, $store, $year, $month);
            $this->assertWorker($admin, $worker);
            if ($amount <= 0) {
                $this->fail('amount', Typer::assertString(\__('The adjustment amount must be greater than zero.')));
            }
            if ($type === PayrollAdjustmentTypeEnum::DEDUCTION) {
                $payslip = $this->payslipForWorker($this->build($admin, $store, $year, $month), $worker->getKey());
                if (Typer::parseFloat($payslip['final_amount'] ?? null) - $amount < 0) {
                    $this->fail('amount', Typer::assertString(\__('The deduction cannot make the final payroll amount negative.')));
                }
            }

            return $report->adjustments()->create([
                'worker_id' => $worker->getKey(),
                'type' => $type->value,
                'amount' => \round($amount, 2),
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Update an adjustment on an open payroll report.
     */
    public function updateAdjustment(
        User $admin,
        Store $store,
        int $year,
        int $month,
        int $adjustmentId,
        PayrollAdjustmentTypeEnum $type,
        float $amount,
        string $reason,
    ): void {
        DB::transaction(function () use ($admin, $store, $year, $month, $adjustmentId, $type, $amount, $reason): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $adjustment = $report->adjustments()->whereKey($adjustmentId)->firstOrFail();
            $payslip = $this->payslipForWorker($this->build($admin, $store, $year, $month), $adjustment->getWorkerId());
            $currentEffect = $adjustment->getType() === PayrollAdjustmentTypeEnum::TIP
                ? $adjustment->getAmount()
                : -$adjustment->getAmount();
            $newEffect = $type === PayrollAdjustmentTypeEnum::TIP ? $amount : -$amount;
            if ($amount <= 0 || Typer::parseFloat($payslip['final_amount'] ?? null) - $currentEffect + $newEffect < 0) {
                $this->fail('amount', Typer::assertString(\__('The adjustment cannot make the final payroll amount negative.')));
            }
            $adjustment->update([
                'type' => $type->value,
                'amount' => \round($amount, 2),
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Delete an adjustment from an open payroll report.
     */
    public function deleteAdjustment(User $admin, Store $store, int $year, int $month, int $adjustmentId): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month, $adjustmentId): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $adjustment = $report->adjustments()->whereKey($adjustmentId)->firstOrFail();
            if ($adjustment->getType() === PayrollAdjustmentTypeEnum::TIP) {
                $payslip = $this->payslipForWorker($this->build($admin, $store, $year, $month), $adjustment->getWorkerId());
                if (Typer::parseFloat($payslip['final_amount'] ?? null) - $adjustment->getAmount() < 0) {
                    $this->fail('amount', Typer::assertString(\__('The adjustment cannot make the final payroll amount negative.')));
                }
            }
            $adjustment->delete();
        });
    }

    /**
     * Close and snapshot a payroll report.
     */
    public function close(User $admin, Store $store, int $year, int $month): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $report = PayrollReport::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $snapshot = $this->build($admin, $store, $year, $month);
            unset($snapshot['id'], $snapshot['status'], $snapshot['closed_at'], $snapshot['reopened_at']);
            $report->update([
                'status' => PayrollReportStatusEnum::CLOSED->value,
                'snapshot' => $snapshot,
                'closed_at' => CarbonImmutable::now(),
                'closed_by_user_id' => $admin->getKey(),
            ]);
        });
    }

    /**
     * Reopen a payroll report after the financial report is open.
     */
    public function reopen(User $admin, Store $store, int $year, int $month): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month): void {
            $report = $this->findReport($admin, $store, $year, $month);
            if (!$report instanceof PayrollReport) {
                $this->fail('report', Typer::assertString(\__('The payroll report does not exist.')));
            }
            $financialQuery = FinancialReport::query();
            FinancialReport::scopeForUser($financialQuery, $admin);
            $financialReport = $financialQuery
                ->where('store_id', $store->getKey())
                ->where('year', $year)
                ->where('month', $month)
                ->first();
            if ($financialReport?->isClosed() === true) {
                $this->fail('report', Typer::assertString(\__('Reopen the financial report before reopening payroll.')));
            }
            $report = PayrollReport::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $report->update([
                'status' => PayrollReportStatusEnum::OPEN->value,
                'reopened_at' => CarbonImmutable::now(),
                'reopened_by_user_id' => $admin->getKey(),
            ]);
        });
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
     * Find or create an open payroll report.
     */
    private function openReport(User $admin, Store $store, int $year, int $month): PayrollReport
    {
        $this->assertStore($admin, $store);
        $report = PayrollReport::query()->firstOrCreate(
            ['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'year' => $year, 'month' => $month],
            ['status' => PayrollReportStatusEnum::OPEN->value],
        );
        if ($report->isClosed()) {
            $this->fail('report', Typer::assertString(\__('The payroll report is closed. Reopen it before making changes.')));
        }

        return $report;
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

    /**
     * Enforce worker ownership.
     */
    private function assertWorker(User $admin, Worker $worker): void
    {
        if ($worker->getUserId() !== $admin->getKey()) {
            \abort(404);
        }
    }

    /**
     * Raise a validation-style domain error.
     */
    private function fail(string $key, string $message): never
    {
        Thrower::default()->message($key, $message)->throw();
    }
}
