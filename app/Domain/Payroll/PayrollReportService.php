<?php

declare(strict_types=1);

namespace App\Domain\Payroll;

use App\Enums\OperationalActivityTypeEnum;
use App\Enums\PayrollAdjustmentTypeEnum;
use App\Enums\PayrollReportStatusEnum;
use App\Models\FinancialReport;
use App\Models\PayrollAdjustment;
use App\Models\PayrollReport;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Support\Money;
use App\Support\OperationalActivityService;
use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class PayrollReportService
{
    /**
     * Include a worker with no activity in an open monthly payroll report.
     */
    public function addWorker(User $admin, Store $store, int $year, int $month, Worker $worker): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month, $worker): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $worker = $this->lockedActiveWorker($admin, $worker);
            if ((new PayrollReportReadService())->buildExactPayslip($admin, $store, $year, $month, $worker->getKey()) !== []) {
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
            $payslip = (new PayrollReportReadService())->buildExactPayslip($admin, $store, $year, $month, $workerId);
            if (($payslip['can_remove'] ?? false) !== true) {
                $this->fail('worker_id', Typer::assertString(\__('This payroll entry is not empty and cannot be removed.')));
            }
            $entry->delete();
        });
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
        float|int|string $hours,
        float|int|string $hourlyRate,
    ): void {
        DB::transaction(function () use ($admin, $store, $year, $month, $worker, $hours, $hourlyRate): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $worker = $this->lockedActiveWorker($admin, $worker);
            $payslip = (new PayrollReportReadService())->buildExactPayslip($admin, $store, $year, $month, $worker->getKey());
            $normalizedHours = Money::of($hours);
            $normalizedHourlyRate = Money::of($hourlyRate);
            $baseAmount = $normalizedHours->multipliedBy($normalizedHourlyRate)->toScale(2, RoundingMode::HalfUp);
            if ($normalizedHours->isNegative() || $normalizedHourlyRate->isNegative() || $baseAmount
                ->plus($this->payslipDecimal($payslip, 'tip_amount'))
                ->minus($this->payslipDecimal($payslip, 'deduction_amount'))
                ->isNegative()) {
                $this->fail('hours', Typer::assertString(\__('The wage override cannot make the final payroll amount negative.')));
            }
            $report->wageOverrides()->updateOrCreate(
                ['worker_id' => $worker->getKey()],
                ['hours' => (string) $normalizedHours, 'hourly_rate' => (string) $normalizedHourlyRate],
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
            $payslip = (new PayrollReportReadService())->buildExactPayslip($admin, $store, $year, $month, $workerId);
            if ($this->payslipDecimal($payslip, 'automatic_base_amount')
                ->plus($this->payslipDecimal($payslip, 'tip_amount'))
                ->minus($this->payslipDecimal($payslip, 'deduction_amount'))
                ->isNegative()) {
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
        float|int|string $amount,
        string $reason,
    ): PayrollAdjustment {
        return DB::transaction(function () use ($admin, $store, $year, $month, $worker, $type, $amount, $reason): PayrollAdjustment {
            $report = $this->openReport($admin, $store, $year, $month);
            $worker = $this->lockedActiveWorker($admin, $worker);
            $normalizedAmount = Money::of($amount);
            if (!$normalizedAmount->isPositive()) {
                $this->fail('amount', Typer::assertString(\__('The adjustment amount must be greater than zero.')));
            }
            if ($type === PayrollAdjustmentTypeEnum::DEDUCTION) {
                $payslip = (new PayrollReportReadService())->buildExactPayslip($admin, $store, $year, $month, $worker->getKey());
                if ($this->payslipDecimal($payslip, 'final_amount')
                    ->minus($normalizedAmount)
                    ->isNegative()) {
                    $this->fail('amount', Typer::assertString(\__('The deduction cannot make the final payroll amount negative.')));
                }
            }

            return $report->adjustments()->create([
                'worker_id' => $worker->getKey(),
                'type' => $type->value,
                'amount' => (string) $normalizedAmount,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Distribute one tip amount between workers by their payable hours.
     */
    public function distributeTips(User $admin, Store $store, int $year, int $month, float|int|string $amount): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month, $amount): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $totalCents = Money::of($amount)
                ->multipliedBy(100)
                ->toScale(0, RoundingMode::HalfUp)
                ->toInt();
            if ($totalCents <= 0) {
                $this->fail('amount', Typer::assertString(\__('The tip amount must be greater than zero.')));
            }

            $allocations = [];
            $totalWeight = 0;
            foreach (Typer::assertArray((new PayrollReportReadService())->buildExact($admin, $store, $year, $month)['payslips'] ?? null) as $value) {
                $payslip = Typer::assertStringKeyArray(Typer::assertArray($value));
                $weight = $this->payslipDecimal($payslip, 'payable_hours')
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
                    'amount' => (string) BigDecimal::of($allocation['share_cents'])->dividedBy(100, 2),
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
        float|int|string $amount,
        string $reason,
    ): void {
        DB::transaction(function () use ($admin, $store, $year, $month, $adjustmentId, $type, $amount, $reason): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $adjustment = $report->adjustments()->whereKey($adjustmentId)->firstOrFail();
            $payslip = (new PayrollReportReadService())->buildExactPayslip($admin, $store, $year, $month, $adjustment->getWorkerId());
            $currentEffect = $adjustment->getType() === PayrollAdjustmentTypeEnum::TIP
                ? Money::of($adjustment->getAmountDecimal())
                : Money::of($adjustment->getAmountDecimal())->negated();
            $normalizedAmount = Money::of($amount);
            $newEffect = $type === PayrollAdjustmentTypeEnum::TIP ? $normalizedAmount : $normalizedAmount->negated();
            if (!$normalizedAmount->isPositive() || $this->payslipDecimal($payslip, 'final_amount')
                ->minus($currentEffect)
                ->plus($newEffect)
                ->isNegative()) {
                $this->fail('amount', Typer::assertString(\__('The adjustment cannot make the final payroll amount negative.')));
            }
            $adjustment->update([
                'type' => $type->value,
                'amount' => (string) $normalizedAmount,
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
                $payslip = (new PayrollReportReadService())->buildExactPayslip($admin, $store, $year, $month, $adjustment->getWorkerId());
                if ($this->payslipDecimal($payslip, 'final_amount')
                    ->minus(Money::of($adjustment->getAmountDecimal()))
                    ->isNegative()) {
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
            $snapshot = (new PayrollReportReadService())->build($admin, $store, $year, $month);
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
            $store = $this->lockedActiveStore($admin, $store);
            $financialQuery = FinancialReport::query();
            FinancialReport::scopeForUser($financialQuery, $admin);
            $financialReport = $financialQuery
                ->where('store_id', $store->getKey())
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();
            if ($financialReport?->isClosed() === true) {
                $this->fail('report', Typer::assertString(\__('Reopen the financial report before reopening payroll.')));
            }
            $payrollQuery = PayrollReport::query();
            PayrollReport::scopeForUser($payrollQuery, $admin);
            $report = $payrollQuery
                ->where('store_id', $store->getKey())
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();
            if (!$report instanceof PayrollReport) {
                $this->fail('report', Typer::assertString(\__('The payroll report does not exist.')));
            }
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
     * Find or create an open payroll report.
     */
    private function openReport(User $admin, Store $store, int $year, int $month): PayrollReport
    {
        $store = $this->lockedActiveStore($admin, $store);
        $report = PayrollReport::query()->firstOrCreate(
            ['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'year' => $year, 'month' => $month],
            ['status' => PayrollReportStatusEnum::OPEN->value],
        );
        $report = PayrollReport::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
        if ($report->isClosed()) {
            $this->fail('report', Typer::assertString(\__('The payroll report is closed. Reopen it before making changes.')));
        }

        return $report;
    }

    /**
     * Lock and recheck a store before any prospective period mutation.
     */
    private function lockedActiveStore(User $admin, Store $store): Store
    {
        $store = Typer::assertInstance(
            Store::query()->whereKey($store->getKey())->lockForUpdate()->firstOrFail(),
            Store::class,
        );
        $this->assertActiveStore($admin, $store);

        return $store;
    }

    /**
     * Read one exact decimal from an internal payslip.
     *
     * @param array<string, mixed> $payslip
     */
    private function payslipDecimal(array $payslip, string $key): BigDecimal
    {
        if (!\array_key_exists($key, $payslip)) {
            return Money::zero();
        }

        return Typer::assertInstance($payslip[$key] ?? null, BigDecimal::class);
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
     * Enforce mutation scope for an active retail store.
     */
    private function assertActiveStore(User $admin, Store $store): void
    {
        $this->assertStore($admin, $store);
        if (!$store->isActive()) {
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
        if ($worker->isArchived()) {
            $this->fail('worker_id', Typer::assertString(\__('Archived workers cannot receive new work.')));
        }
    }

    /**
     * Lock and recheck a worker before adding prospective payroll work.
     */
    private function lockedActiveWorker(User $admin, Worker $worker): Worker
    {
        $worker = Typer::assertInstance(
            Worker::query()->whereKey($worker->getKey())->lockForUpdate()->firstOrFail(),
            Worker::class,
        );
        $this->assertWorker($admin, $worker);

        return $worker;
    }

    /**
     * Raise a validation-style domain error.
     */
    private function fail(string $key, string $message): never
    {
        Thrower::default()->message($key, $message)->throw();
    }
}
