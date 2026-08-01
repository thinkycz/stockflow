<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FinancialDirectionEnum;
use App\Enums\FinancialReportStatusEnum;
use App\Enums\FinancialSourceTypeEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\FinancialReport;
use App\Models\FinancialReportManualRow;
use App\Models\FinancialReportOverride;
use App\Models\PayrollReport;
use App\Models\Statement;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * @phpstan-type FinancialRow array{id: string, kind: string, direction: string, source_type: string|null, source_key: string|null, label: string, occurred_on: string|null, calculated_amount: float, override_amount: float|null, effective_amount: float, note: string|null, details: array<string, mixed>, manual_row_id?: int}
 * @phpstan-type FinancialPayload array{income_rows: list<FinancialRow>, expense_rows: list<FinancialRow>, totals: array{income: float, expenses: float, profit: float}}
 */
class FinancialReportService
{
    /**
     * Revenue channel definitions and monthly commission rates.
     *
     * @var array<string, float>
     */
    private const array REVENUE_RATES = [
        'cash' => 0.0,
        'card' => 0.01,
        'bolt' => 0.30,
        'wolt' => 0.30,
        'foodora' => 0.30,
    ];

    /**
     * Build a live report or return its closed snapshot.
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

        $rows = [
            ...$this->revenueRows($admin, $store, $year, $month),
            ...$this->stockRows($admin, $store, $year, $month),
            ...$this->wageRows($admin, $store, $year, $month),
        ];
        $overrides = [];

        if ($report instanceof FinancialReport) {
            foreach ($report->getOverrides() as $override) {
                $overrides[$override->getSourceType()->value . ':' . $override->getSourceKey()] = $override->getAmount();
            }
        }

        foreach ($rows as &$row) {
            $key = $row['source_type'] . ':' . $row['source_key'];
            $override = $overrides[$key] ?? null;
            $row['override_amount'] = $override;
            $row['effective_amount'] = $override ?? $row['calculated_amount'];
        }
        unset($row);

        if ($report instanceof FinancialReport) {
            foreach ($report->getManualRows() as $manualRow) {
                $rows[] = $this->manualPayload($manualRow);
            }
        }

        $payload = $this->summarize($rows);

        return \array_merge($payload, $this->reportMeta($report));
    }

    /**
     * Store or replace an automatic-row override.
     */
    public function setOverride(User $admin, Store $store, int $year, int $month, FinancialSourceTypeEnum $sourceType, string $sourceKey, float $amount): void
    {
        $report = $this->openReport($admin, $store, $year, $month);
        $exists = false;
        foreach ([...$this->revenueRows($admin, $store, $year, $month), ...$this->stockRows($admin, $store, $year, $month), ...$this->wageRows($admin, $store, $year, $month)] as $row) {
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
    }

    /**
     * Remove an automatic-row override.
     */
    public function resetOverride(User $admin, Store $store, int $year, int $month, FinancialSourceTypeEnum $sourceType, string $sourceKey): void
    {
        $report = $this->requireOpenReport($admin, $store, $year, $month);
        $report->overrides()->where('source_type', $sourceType->value)->where('source_key', $sourceKey)->delete();
    }

    /**
     * Create a manual row.
     */
    public function createManualRow(User $admin, Store $store, int $year, int $month, FinancialDirectionEnum $direction, string $label, string $occurredOn, float $amount, string|null $note): FinancialReportManualRow
    {
        return $this->openReport($admin, $store, $year, $month)->manualRows()->create([
            'direction' => $direction->value,
            'label' => $label,
            'occurred_on' => $occurredOn,
            'amount' => \round($amount, 2),
            'note' => $note,
        ]);
    }

    /**
     * Update a manual row.
     */
    public function updateManualRow(User $admin, Store $store, int $year, int $month, int $rowId, FinancialDirectionEnum $direction, string $label, string $occurredOn, float $amount, string|null $note): void
    {
        $row = $this->manualRow($this->requireOpenReport($admin, $store, $year, $month), $rowId);
        $row->update(['direction' => $direction->value, 'label' => $label, 'occurred_on' => $occurredOn, 'amount' => \round($amount, 2), 'note' => $note]);
    }

    /**
     * Delete a manual row.
     */
    public function deleteManualRow(User $admin, Store $store, int $year, int $month, int $rowId): void
    {
        $this->manualRow($this->requireOpenReport($admin, $store, $year, $month), $rowId)->delete();
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
            $source = $this->findReport($admin, $store, $previous->year, $previous->month);

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
     * Close and snapshot a monthly report.
     */
    public function close(User $admin, Store $store, int $year, int $month): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month): void {
            $payrollQuery = PayrollReport::query();
            PayrollReport::scopeForUser($payrollQuery, $admin);
            $payrollReport = $payrollQuery
                ->where('store_id', $store->getKey())
                ->where('year', $year)
                ->where('month', $month)
                ->first();
            if (!$payrollReport instanceof PayrollReport || !$payrollReport->isClosed()) {
                Thrower::default()->message('report', Typer::assertString(\__('Close the payroll report before closing the financial report.')))->throw();
            }
            $report = $this->openReport($admin, $store, $year, $month);
            $report = FinancialReport::query()->lockForUpdate()->findOrFail($report->getKey());
            if ($report->isClosed()) {
                $this->failClosed();
            }
            $payload = $this->build($admin, $store, $year, $month);
            unset($payload['report']);
            $report->update([
                'status' => FinancialReportStatusEnum::CLOSED->value,
                'snapshot' => $payload,
                'closed_at' => CarbonImmutable::now(),
                'closed_by_user_id' => $admin->getKey(),
            ]);
        });
    }

    /**
     * Reopen a closed report and resume live source calculations.
     */
    public function reopen(User $admin, Store $store, int $year, int $month): void
    {
        DB::transaction(function () use ($admin, $store, $year, $month): void {
            $report = $this->findReport($admin, $store, $year, $month);
            if (!$report instanceof FinancialReport) {
                Thrower::default()->message('report', \__('The financial report does not exist.'))->throw();
            }
            $report = FinancialReport::query()->lockForUpdate()->findOrFail($report->getKey());
            $report->update([
                'status' => FinancialReportStatusEnum::OPEN->value,
                'reopened_at' => CarbonImmutable::now(),
                'reopened_by_user_id' => $admin->getKey(),
            ]);
        });
    }

    /**
     * @return list<FinancialRow>
     */
    private function revenueRows(User $admin, Store $store, int $year, int $month): array
    {
        $query = Statement::query();
        Statement::scopeForUser($query, $admin);
        Statement::scopeForStore($query, $store->getKey());
        Statement::scopeForMonth($query, $year, $month);
        $statement = $query->first();
        $gross = ['cash' => 0.0, 'card' => 0.0, 'bolt' => 0.0, 'wolt' => 0.0, 'foodora' => 0.0];

        if ($statement instanceof Statement) {
            foreach ($statement->getDays() as $day) {
                $gross['cash'] += $day->getCash();
                $gross['card'] += $day->getCard();
                $gross['bolt'] += $day->getBolt() + $day->getBoltCash();
                $gross['wolt'] += $day->getWolt();
                $gross['foodora'] += $day->getFoodora();
            }
        }

        $rows = [];
        foreach (self::REVENUE_RATES as $channel => $rate) {
            $channelGross = \round($gross[$channel], 2);
            $commission = \round($channelGross * $rate, 2);
            $rows[] = $this->automaticRow(FinancialDirectionEnum::INCOME, FinancialSourceTypeEnum::REVENUE, $channel, \ucfirst($channel), null, \round($channelGross - $commission, 2), [
                'gross_amount' => $channelGross,
                'commission_rate' => $rate,
                'commission_amount' => $commission,
            ]);
        }

        return $rows;
    }

    /**
     * @return list<FinancialRow>
     */
    private function stockRows(User $admin, Store $store, int $year, int $month): array
    {
        $start = new CarbonImmutable($year . '-' . $month . '-01 00:00:00', AttendanceService::BUSINESS_TIMEZONE);
        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $admin);
        StockMovement::scopeForStore($query, $store->getKey());
        StockMovement::querySelect($query);

        return \array_values($query
            ->whereIn('type', [StockMovementTypeEnum::INCOMING->value, StockMovementTypeEnum::TRANSFER->value])
            ->whereNull('reversed_at')
            ->where('occurred_at', '>=', $start->utc())
            ->where('occurred_at', '<', $start->addMonth()->utc())
            ->orderBy('occurred_at')
            ->get()
            ->map(fn(StockMovement $movement): array => $this->automaticRow(
                FinancialDirectionEnum::EXPENSE,
                FinancialSourceTypeEnum::STOCK_MOVEMENT,
                (string) $movement->getKey(),
                $movement->getNumber(),
                $movement->getOccurredAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toDateString(),
                \round(\abs($movement->getTotalValue()), 2),
                ['movement_id' => $movement->getKey(), 'movement_type' => $movement->getType()->value],
            ))
            ->values()
            ->all());
    }

    /**
     * @return list<FinancialRow>
     */
    private function wageRows(User $admin, Store $store, int $year, int $month): array
    {
        $rows = [];
        $payroll = (new PayrollReportService())->build($admin, $store, $year, $month);
        foreach (Typer::assertArray($payroll['payslips'] ?? null) as $value) {
            $payslip = Typer::assertStringKeyArray(Typer::assertArray($value));
            $rows[] = $this->automaticRow(
                FinancialDirectionEnum::EXPENSE,
                FinancialSourceTypeEnum::WAGE,
                (string) Typer::assertInt($payslip['worker_id'] ?? null),
                Typer::assertString($payslip['worker_name'] ?? null),
                null,
                Typer::parseFloat($payslip['final_amount'] ?? null),
                [
                    'worker_id' => Typer::assertInt($payslip['worker_id'] ?? null),
                    'minutes' => Typer::assertInt($payslip['planned_minutes'] ?? null),
                    'base_amount' => Typer::parseFloat($payslip['base_amount'] ?? null),
                    'tip_amount' => Typer::parseFloat($payslip['tip_amount'] ?? null),
                    'deduction_amount' => Typer::parseFloat($payslip['deduction_amount'] ?? null),
                ],
            );
        }

        return $rows;
    }

    /**
     * Build a calculated row.
     *
     * @param array<string, mixed> $details
     *
     * @return FinancialRow
     */
    private function automaticRow(FinancialDirectionEnum $direction, FinancialSourceTypeEnum $sourceType, string $sourceKey, string $label, string|null $occurredOn, float $amount, array $details): array
    {
        return [
            'id' => $sourceType->value . ':' . $sourceKey,
            'kind' => 'automatic',
            'direction' => $direction->value,
            'source_type' => $sourceType->value,
            'source_key' => $sourceKey,
            'label' => $label,
            'occurred_on' => $occurredOn,
            'calculated_amount' => $amount,
            'override_amount' => null,
            'effective_amount' => $amount,
            'note' => null,
            'details' => $details,
        ];
    }

    /**
     * @return FinancialRow
     */
    private function manualPayload(FinancialReportManualRow $row): array
    {
        return [
            'id' => 'manual:' . $row->getKey(),
            'manual_row_id' => $row->getKey(),
            'kind' => 'manual',
            'direction' => $row->getDirection()->value,
            'source_type' => null,
            'source_key' => null,
            'label' => $row->getLabel(),
            'occurred_on' => $row->getOccurredOn(),
            'calculated_amount' => $row->getAmount(),
            'override_amount' => null,
            'effective_amount' => $row->getAmount(),
            'note' => $row->getNote(),
            'details' => [],
        ];
    }

    /**
     * Split rows and calculate totals.
     *
     * @param list<FinancialRow> $rows
     *
     * @return FinancialPayload
     */
    private function summarize(array $rows): array
    {
        $income = \array_values(\array_filter($rows, static fn(array $row): bool => $row['direction'] === FinancialDirectionEnum::INCOME->value));
        $expenses = \array_values(\array_filter($rows, static fn(array $row): bool => $row['direction'] === FinancialDirectionEnum::EXPENSE->value));
        \usort($expenses, static fn(array $a, array $b): int => [$a['occurred_on'] ?? '', $a['label']] <=> [$b['occurred_on'] ?? '', $b['label']]);
        $incomeTotal = 0.0;
        foreach ($income as $row) {
            $incomeTotal += $row['effective_amount'];
        }
        $expenseTotal = 0.0;
        foreach ($expenses as $row) {
            $expenseTotal += $row['effective_amount'];
        }
        $incomeTotal = \round($incomeTotal, 2);
        $expenseTotal = \round($expenseTotal, 2);

        return ['income_rows' => $income, 'expense_rows' => $expenses, 'totals' => ['income' => $incomeTotal, 'expenses' => $expenseTotal, 'profit' => \round($incomeTotal - $expenseTotal, 2)]];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportMeta(FinancialReport|null $report): array
    {
        return ['report' => $report instanceof FinancialReport ? [
            'id' => $report->getKey(),
            'status' => $report->getStatus()->value,
            'closed_at' => $report->getClosedAt()?->toIso8601String(),
            'reopened_at' => $report->getReopenedAt()?->toIso8601String(),
        ] : ['id' => null, 'status' => FinancialReportStatusEnum::OPEN->value, 'closed_at' => null, 'reopened_at' => null]];
    }

    /**
     * Find an existing report.
     */
    private function findReport(User $admin, Store $store, int $year, int $month): FinancialReport|null
    {
        $query = FinancialReport::query();
        FinancialReport::scopeForUser($query, $admin);

        return $query->where('store_id', $store->getKey())->where('year', $year)->where('month', $month)->first();
    }

    /**
     * Find or create an open report.
     */
    private function openReport(User $admin, Store $store, int $year, int $month): FinancialReport
    {
        $this->assertStore($admin, $store);
        $report = FinancialReport::query()->firstOrCreate(
            ['user_id' => $admin->getKey(), 'store_id' => $store->getKey(), 'year' => $year, 'month' => $month],
            ['status' => FinancialReportStatusEnum::OPEN->value],
        );
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
        $report = $this->findReport($admin, $store, $year, $month);
        if (!$report instanceof FinancialReport) {
            Thrower::default()->message('report', \__('The financial report does not exist.'))->throw();
        }
        if ($report->isClosed()) {
            $this->failClosed();
        }

        return $report;
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
     * Fail a mutation against a closed report.
     */
    private function failClosed(): never
    {
        Thrower::default()->message('report', \__('The financial report is closed. Reopen it before making changes.'))->throw();
    }
}
