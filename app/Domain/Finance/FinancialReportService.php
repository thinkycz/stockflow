<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Enums\FinancialDirectionEnum;
use App\Enums\FinancialReportStatusEnum;
use App\Enums\FinancialSourceTypeEnum;
use App\Enums\OperationalActivityTypeEnum;
use App\Models\FinancialRecurringExpense;
use App\Models\FinancialRecurringExpenseVersion;
use App\Models\FinancialReport;
use App\Models\FinancialReportManualRow;
use App\Models\FinancialReportOverride;
use App\Models\PayrollReport;
use App\Models\Store;
use App\Models\User;
use App\Support\OperationalActivityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class FinancialReportService
{
    /**
     * Store or replace an automatic-row override.
     */
    public function setOverride(User $admin, Store $store, int $year, int $month, FinancialSourceTypeEnum $sourceType, string $sourceKey, float $amount): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month, $sourceType, $sourceKey, $amount): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $exists = false;
            foreach ((new FinancialReportReadService())->automaticRows($admin, $store, $year, $month) as $row) {
                if ($row['source_type'] === $sourceType->value && $row['source_key'] === $sourceKey) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                Thrower::default()->message('source_key', \__('The selected calculated row no longer exists.'))->throw();
            }

            FinancialReportOverride::query()->updateOrCreate(
                ['financial_report_id' => $report->getKey(), 'source_type' => $sourceType->value, 'source_key' => $sourceKey],
                ['amount' => \round($amount, 2)],
            );
        });
    }

    /**
     * Remove an automatic-row override.
     */
    public function resetOverride(User $admin, Store $store, int $year, int $month, FinancialSourceTypeEnum $sourceType, string $sourceKey): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month, $sourceType, $sourceKey): void {
            $report = $this->requireOpenReport($admin, $store, $year, $month);
            $report->overrides()->where('source_type', $sourceType->value)->where('source_key', $sourceKey)->delete();
        });
    }

    /**
     * Create a manual row.
     */
    public function createManualRow(User $admin, Store $store, int $year, int $month, FinancialDirectionEnum $direction, string $label, string $occurredOn, float $amount, string|null $note): FinancialReportManualRow
    {
        return DB::transaction(fn(): FinancialReportManualRow => $this->openReport($admin, $store, $year, $month)->manualRows()->create([
            'direction' => $direction->value,
            'label' => $label,
            'occurred_on' => $occurredOn,
            'amount' => \round($amount, 2),
            'note' => $note,
        ]));
    }

    /**
     * Update a manual row.
     */
    public function updateManualRow(User $admin, Store $store, int $year, int $month, int $rowId, FinancialDirectionEnum $direction, string $label, string $occurredOn, float $amount, string|null $note): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month, $rowId, $direction, $label, $occurredOn, $amount, $note): void {
            $row = $this->manualRow($this->requireOpenReport($admin, $store, $year, $month), $rowId);
            $row->update(['direction' => $direction->value, 'label' => $label, 'occurred_on' => $occurredOn, 'amount' => \round($amount, 2), 'note' => $note]);
        });
    }

    /**
     * Delete a manual row.
     */
    public function deleteManualRow(User $admin, Store $store, int $year, int $month, int $rowId): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month, $rowId): void {
            $this->manualRow($this->requireOpenReport($admin, $store, $year, $month), $rowId)->delete();
        });
    }

    /**
     * Copy previous-month manual rows without creating duplicates.
     */
    public function copyPreviousManualRows(User $admin, Store $store, int $year, int $month): int
    {
        return DB::transaction(function () use ($admin, $store, $year, $month): int {
            $target = $this->openReport($admin, $store, $year, $month);
            $targetMonth = new CarbonImmutable($year . '-' . $month . '-01');
            $previous = $targetMonth->subMonth();
            $source = (new FinancialReportReadService())->findReport($admin, $store, $previous->year, $previous->month);

            if (!$source instanceof FinancialReport) {
                return 0;
            }

            $count = 0;
            foreach ($source->getManualRows() as $sourceRow) {
                $day = \min((int) CarbonImmutable::parse($sourceRow->getOccurredOn())->format('j'), $targetMonth->daysInMonth);
                $row = $target->manualRows()->firstOrCreate(
                    ['copied_from_row_id' => $sourceRow->getKey()],
                    [
                        'direction' => $sourceRow->getDirection()->value,
                        'label' => $sourceRow->getLabel(),
                        'occurred_on' => $targetMonth->setDay($day)->toDateString(),
                        'amount' => $sourceRow->getAmount(),
                        'note' => $sourceRow->getNote(),
                    ],
                );
                if ($row->wasRecentlyCreated) {
                    ++$count;
                }
            }

            return $count;
        });
    }

    /**
     * Create a recurring monthly expense and its first effective version.
     */
    public function createRecurringExpense(User $admin, Store $store, int $year, int $month, string $label, float $amount, int $dueDay, string|null $note): FinancialRecurringExpense
    {
        $this->assertStore($admin, $store);

        return DB::transaction(function () use ($admin, $store, $year, $month, $label, $amount, $dueDay, $note): FinancialRecurringExpense {
            $this->openReport($admin, $store, $year, $month);
            $startsOn = new CarbonImmutable($year . '-' . $month . '-01');
            $expense = FinancialRecurringExpense::query()->create([
                'user_id' => $admin->getKey(),
                'store_id' => $store->getKey(),
                'starts_on' => $startsOn->toDateString(),
            ]);
            $expense->versions()->create([
                'effective_from' => $startsOn->toDateString(),
                'label' => $label,
                'amount' => \round($amount, 2),
                'due_day' => $dueDay,
                'note' => $note,
            ]);

            return $expense;
        });
    }

    /**
     * Add or replace a recurring-expense version from an effective month.
     */
    public function changeRecurringExpense(User $admin, Store $store, int $expenseId, int $year, int $month, string $label, float $amount, int $dueDay, string|null $note): void
    {
        $this->assertStore($admin, $store);
        DB::transaction(function () use ($admin, $store, $expenseId, $year, $month, $label, $amount, $dueDay, $note): void {
            $this->openReport($admin, $store, $year, $month);
            $expense = $this->recurringExpense($admin, $store, $expenseId, true);
            $effectiveFrom = new CarbonImmutable($year . '-' . $month . '-01');
            if ($effectiveFrom->toDateString() < $expense->getStartsOn()) {
                Thrower::default()->message('effective_from', \__('A recurring expense change cannot start before the expense itself.'))->throw();
            }
            if ($expense->getEndsBefore() !== null) {
                Thrower::default()->message('recurring_expense', \__('An ended recurring expense cannot be changed.'))->throw();
            }
            $version = $expense->versions()->whereDate('effective_from', $effectiveFrom->toDateString())->first();
            $attributes = ['label' => $label, 'amount' => \round($amount, 2), 'due_day' => $dueDay, 'note' => $note];
            if ($version instanceof FinancialRecurringExpenseVersion) {
                $version->update($attributes);
            } else {
                $expense->versions()->create(['effective_from' => $effectiveFrom->toDateString(), ...$attributes]);
            }
        });
    }

    /**
     * End a recurring expense before the selected month.
     */
    public function terminateRecurringExpense(User $admin, Store $store, int $expenseId, int $year, int $month): void
    {
        $this->assertStore($admin, $store);
        DB::transaction(function () use ($admin, $store, $expenseId, $year, $month): void {
            $this->openReport($admin, $store, $year, $month);
            $expense = $this->recurringExpense($admin, $store, $expenseId, true);
            $endsBefore = new CarbonImmutable($year . '-' . $month . '-01');
            if ($endsBefore->toDateString() < $expense->getStartsOn()) {
                Thrower::default()->message('ends_before', \__('A recurring expense cannot end before it starts.'))->throw();
            }
            if ($expense->getEndsBefore() !== null) {
                Thrower::default()->message('recurring_expense', \__('The recurring expense has already ended.'))->throw();
            }
            $expense->update(['ends_before' => $endsBefore->toDateString()]);
        });
    }

    /**
     * Close and snapshot a monthly report.
     */
    public function close(User $admin, Store $store, int $year, int $month): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month): void {
            $report = $this->openReport($admin, $store, $year, $month);
            $payrollQuery = PayrollReport::query();
            PayrollReport::scopeForUser($payrollQuery, $admin);
            $payrollReport = $payrollQuery
                ->where('store_id', $store->getKey())
                ->where('year', $year)
                ->where('month', $month)
                ->lockForUpdate()
                ->first();
            if (!$payrollReport instanceof PayrollReport || !$payrollReport->isClosed()) {
                Thrower::default()->message('report', Typer::assertString(\__('Close the payroll report before closing the financial report.')))->throw();
            }
            $payload = (new FinancialReportReadService())->build($admin, $store, $year, $month);
            unset($payload['report']);
            $report->update([
                'status' => FinancialReportStatusEnum::CLOSED->value,
                'snapshot' => $payload,
                'closed_at' => CarbonImmutable::now(),
                'closed_by_user_id' => $admin->getKey(),
            ]);
            $this->notifyLifecycle(OperationalActivityTypeEnum::FINANCIAL_REPORT_CLOSED, $admin, $store, $year, $month, $payload);
        });
    }

    /**
     * Reopen a closed report and resume live source calculations.
     */
    public function reopen(User $admin, Store $store, int $year, int $month): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month): void {
            $store = $this->lockedActiveStore($admin, $store);
            $report = (new FinancialReportReadService())->findReport($admin, $store, $year, $month);
            if (!$report instanceof FinancialReport) {
                Thrower::default()->message('report', \__('The financial report does not exist.'))->throw();
            }
            $report = FinancialReport::query()->lockForUpdate()->findOrFail($report->getKey());
            $wasClosed = $report->isClosed();
            $snapshot = $report->getSnapshot() ?? ['totals' => ['income' => 0, 'expenses' => 0, 'profit' => 0]];
            $report->update([
                'status' => FinancialReportStatusEnum::OPEN->value,
                'reopened_at' => CarbonImmutable::now(),
                'reopened_by_user_id' => $admin->getKey(),
            ]);
            if ($wasClosed) {
                $this->notifyLifecycle(OperationalActivityTypeEnum::FINANCIAL_REPORT_REOPENED, $admin, $store, $year, $month, $snapshot);
            }
        });
    }

    /**
     * Dispatch one financial report lifecycle milestone.
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
        $totals = Typer::assertStringKeyArray(Typer::assertArray($snapshot['totals'] ?? null));
        OperationalActivityService::dispatch(
            $type,
            $admin,
            CarbonImmutable::now('UTC')->toIso8601String(),
            Resolver::resolveUrlGenerator()->route('income-expenses.index', ['year' => $year, 'month' => $month]),
            [['store' => $store, 'perspective' => null]],
            [
                'Slack report month' => \sprintf('%02d/%d', $month, $year),
                'Slack financial income' => $this->formatCurrency(Typer::parseFloat($totals['income'] ?? null)),
                'Slack financial expenses' => $this->formatCurrency(Typer::parseFloat($totals['expenses'] ?? null)),
                'Slack financial profit' => $this->formatCurrency(Typer::parseFloat($totals['profit'] ?? null)),
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
     * Resolve a recurring expense inside the active administrator and store scope.
     */
    private function recurringExpense(User $admin, Store $store, int $expenseId, bool $lock): FinancialRecurringExpense
    {
        $query = FinancialRecurringExpense::query();
        FinancialRecurringExpense::scopeForUser($query, $admin);
        FinancialRecurringExpense::querySelect($query);
        $query->where('store_id', $store->getKey())->whereKey($expenseId);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }

    /**
     * Find or create an open report.
     */
    private function openReport(User $admin, Store $store, int $year, int $month): FinancialReport
    {
        $store = $this->lockedActiveStore($admin, $store);
        $report = FinancialReport::query()->firstOrCreate(
            ['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'year' => $year, 'month' => $month],
            ['status' => FinancialReportStatusEnum::OPEN->value],
        );
        $report = FinancialReport::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
        if ($report->isClosed()) {
            $this->failClosed();
        }

        return $report;
    }

    /**
     * Require an existing open report.
     */
    private function requireOpenReport(User $admin, Store $store, int $year, int $month): FinancialReport
    {
        $store = $this->lockedActiveStore($admin, $store);
        $query = FinancialReport::query();
        FinancialReport::scopeForUser($query, $admin);
        $report = $query
            ->where('store_id', $store->getKey())
            ->where('year', $year)
            ->where('month', $month)
            ->lockForUpdate()
            ->first();
        if (!$report instanceof FinancialReport) {
            Thrower::default()->message('report', \__('The financial report does not exist.'))->throw();
        }
        if ($report->isClosed()) {
            $this->failClosed();
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
     * Resolve a manual row inside the selected report.
     */
    private function manualRow(FinancialReport $report, int $rowId): FinancialReportManualRow
    {
        return $report->manualRows()->whereKey($rowId)->firstOrFail();
    }

    /**
     * Enforce company ownership and retail-only scope.
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
     * Fail a mutation against a closed report.
     */
    private function failClosed(): never
    {
        Thrower::default()->message('report', \__('The financial report is closed. Reopen it before making changes.'))->throw();
    }
}
