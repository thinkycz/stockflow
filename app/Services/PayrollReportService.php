<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationalActivityTypeEnum;
use App\Enums\PayrollAdjustmentTypeEnum;
use App\Enums\PayrollReportStatusEnum;
use App\Models\FinancialReport;
use App\Models\PayrollAdjustment;
use App\Models\PayrollReport;
use App\Models\PayrollWageOverride;
use App\Models\PayrollWorkerEntry;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
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

            $automaticBaseAmount = $baseAmount;
            $automaticHours = \round($plannedMinutes / 60, 2);
            $automaticHourlyRate = $automaticHours > 0
                ? \round($automaticBaseAmount / $automaticHours, 2)
                : ($manuallyAdded ? $worker->getHourlyRate() : 0.0);
            if ($wageOverride instanceof PayrollWageOverride) {
                $baseAmount = \round($wageOverride->getHours() * $wageOverride->getHourlyRate(), 2);
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
                'automatic_base_amount' => $automaticBaseAmount,
                'payable_hours' => $wageOverride instanceof PayrollWageOverride ? $wageOverride->getHours() : $automaticHours,
                'payable_hourly_rate' => $wageOverride instanceof PayrollWageOverride ? $wageOverride->getHourlyRate() : $automaticHourlyRate,
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
     * Include a worker with no activity in an open monthly payroll report.
     */
    public function addWorker(User $admin, Store $store, int $year, int $month, Worker $worker): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month, $worker): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $this->assertWorker($admin, $worker);
            if ($this->payslipForWorker($this->build($admin, $store, $year, $month), $worker->getKey()) !== []) {
                $this->fail('worker_id', Typer::assertString(\__('This worker already has a payroll entry.')));
            }
            $report->workerEntries()->create(['worker_id' => $worker->getKey()]);
        });
    }

    /**
     * Remove a manually included worker when their payroll entry is empty.
     */
    public function removeWorker(User $admin, Store $store, int $year, int $month, int $workerId): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month, $workerId): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $entry = $report->workerEntries()->where('worker_id', $workerId)->firstOrFail();
            $payslip = $this->payslipForWorker($this->build($admin, $store, $year, $month), $workerId);
            if (($payslip['can_remove'] ?? false) !== true) {
                $this->fail('worker_id', Typer::assertString(\__('This payroll entry is not empty and cannot be removed.')));
            }
            $entry->delete();
        });
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
     * Create or replace a worker's monthly wage override.
     */
    public function upsertWageOverride(
        User $admin,
        Store $store,
        int $year,
        int $month,
        Worker $worker,
        float $hours,
        float $hourlyRate,
    ): void {
        DB::transaction(function () use ($admin, $store, $year, $month, $worker, $hours, $hourlyRate): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $this->assertWorker($admin, $worker);
            $payslip = $this->payslipForWorker($this->build($admin, $store, $year, $month), $worker->getKey());
            $baseAmount = \round($hours * $hourlyRate, 2);
            if ($hours < 0 || $hourlyRate < 0 || $baseAmount
                + Typer::parseFloat($payslip['tip_amount'] ?? 0)
                - Typer::parseFloat($payslip['deduction_amount'] ?? 0) < 0) {
                $this->fail('hours', Typer::assertString(\__('The wage override cannot make the final payroll amount negative.')));
            }
            $report->wageOverrides()->updateOrCreate(
                ['worker_id' => $worker->getKey()],
                ['hours' => \round($hours, 2), 'hourly_rate' => \round($hourlyRate, 2)],
            );
        });
    }

    /**
     * Restore a worker's automatic monthly wage calculation.
     */
    public function deleteWageOverride(User $admin, Store $store, int $year, int $month, int $workerId): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month, $workerId): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $override = $report->wageOverrides()->where('worker_id', $workerId)->firstOrFail();
            $payslip = $this->payslipForWorker($this->build($admin, $store, $year, $month), $workerId);
            if (Typer::parseFloat($payslip['automatic_base_amount'] ?? 0)
                + Typer::parseFloat($payslip['tip_amount'] ?? 0)
                - Typer::parseFloat($payslip['deduction_amount'] ?? 0) < 0) {
                $this->fail('hours', Typer::assertString(\__('Restoring automatic wages cannot make the final payroll amount negative.')));
            }
            $override->delete();
        });
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
     * Distribute one tip amount between workers by their payable hours.
     */
    public function distributeTips(User $admin, Store $store, int $year, int $month, float $amount): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month, $amount): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $totalCents = BigDecimal::of((string) $amount)
                ->multipliedBy(100)
                ->toScale(0, RoundingMode::HalfUp)
                ->toInt();
            if ($totalCents <= 0) {
                $this->fail('amount', Typer::assertString(\__('The tip amount must be greater than zero.')));
            }

            $allocations = [];
            $totalWeight = 0;
            foreach (Typer::assertArray($this->build($admin, $store, $year, $month)['payslips'] ?? null) as $value) {
                $payslip = Typer::assertStringKeyArray(Typer::assertArray($value));
                $weight = BigDecimal::of((string) Typer::parseFloat($payslip['payable_hours'] ?? null))
                    ->multipliedBy(100)
                    ->toScale(0, RoundingMode::HalfUp)
                    ->toInt();
                if ($weight <= 0) {
                    continue;
                }
                $allocations[] = [
                    'worker_id' => Typer::assertInt($payslip['worker_id'] ?? null),
                    'weight' => $weight,
                    'remainder' => 0,
                    'share_cents' => 0,
                ];
                $totalWeight += $weight;
            }
            if ($allocations === []) {
                $this->fail('amount', Typer::assertString(\__('Tips cannot be distributed because no worker has payable hours.')));
            }

            $allocatedCents = 0;
            foreach ($allocations as $index => $allocation) {
                [$share, $remainder] = BigInteger::of($totalCents)
                    ->multipliedBy($allocation['weight'])
                    ->quotientAndRemainder($totalWeight);
                $allocations[$index]['share_cents'] = $share->toInt();
                $allocations[$index]['remainder'] = $remainder->toInt();
                $allocatedCents += $share->toInt();
            }
            \usort($allocations, static function (array $left, array $right): int {
                $remainderOrder = $right['remainder'] <=> $left['remainder'];

                return $remainderOrder !== 0 ? $remainderOrder : $left['worker_id'] <=> $right['worker_id'];
            });
            for ($index = 0; $index < $totalCents - $allocatedCents; ++$index) {
                ++$allocations[$index]['share_cents'];
            }

            foreach ($allocations as $allocation) {
                if ($allocation['share_cents'] === 0) {
                    continue;
                }
                $report->adjustments()->create([
                    'worker_id' => $allocation['worker_id'],
                    'type' => PayrollAdjustmentTypeEnum::TIP->value,
                    'amount' => \number_format($allocation['share_cents'] / 100, 2, '.', ''),
                    'reason' => Typer::assertString(\__('Proportionally distributed tips')),
                ]);
            }
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
            $this->notifyLifecycle(OperationalActivityTypeEnum::PAYROLL_REPORT_CLOSED, $admin, $store, $year, $month, $snapshot);
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
            $wasClosed = $report->isClosed();
            $snapshot = $report->getSnapshot() ?? ['payslips' => []];
            $report->update([
                'status' => PayrollReportStatusEnum::OPEN->value,
                'reopened_at' => CarbonImmutable::now(),
                'reopened_by_user_id' => $admin->getKey(),
            ]);
            if ($wasClosed) {
                $this->notifyLifecycle(OperationalActivityTypeEnum::PAYROLL_REPORT_REOPENED, $admin, $store, $year, $month, $snapshot);
            }
        });
    }

    /**
     * Dispatch one payroll report lifecycle milestone.
     *
     * @param array<string, mixed> $snapshot
     */
    private function notifyLifecycle(
        OperationalActivityTypeEnum $type,
        User $admin,
        Store $store,
        int $year,
        int $month,
        array $snapshot,
    ): void {
        $base = 0.0;
        $tips = 0.0;
        $deductions = 0.0;
        $final = 0.0;
        $payslips = Typer::assertArray($snapshot['payslips'] ?? []);
        foreach ($payslips as $value) {
            $payslip = Typer::assertStringKeyArray(Typer::assertArray($value));
            $base += Typer::parseFloat($payslip['base_amount'] ?? null);
            $tips += Typer::parseFloat($payslip['tip_amount'] ?? null);
            $deductions += Typer::parseFloat($payslip['deduction_amount'] ?? null);
            $final += Typer::parseFloat($payslip['final_amount'] ?? null);
        }

        OperationalActivityService::dispatch(
            $type,
            $admin,
            CarbonImmutable::now('UTC')->toIso8601String(),
            Resolver::resolveUrlGenerator()->route('payroll.index', ['year' => $year, 'month' => $month]),
            [['store' => $store, 'perspective' => null]],
            [
                'Slack report month' => \sprintf('%02d/%d', $month, $year),
                'Slack payslip count' => (string) \count($payslips),
                'Slack payroll base' => $this->formatCurrency($base),
                'Slack payroll tips' => $this->formatCurrency($tips),
                'Slack payroll deductions' => $this->formatCurrency($deductions),
                'Slack payroll final' => $this->formatCurrency($final),
            ],
        );
    }

    /**
     * Format a CZK notification amount.
     */
    private function formatCurrency(float $amount): string
    {
        return \number_format($amount, 2, ',', ' ') . ' Kč';
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
