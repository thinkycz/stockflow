<?php

declare(strict_types=1);

namespace App\Domain\Finance;

use App\Domain\Payroll\PayrollReportReadService;
use App\Domain\Workforce\AttendanceService;
use App\Enums\FinancialDirectionEnum;
use App\Enums\FinancialReportStatusEnum;
use App\Enums\FinancialSourceTypeEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\FinancialRecurringExpense;
use App\Models\FinancialRecurringExpenseVersion;
use App\Models\FinancialReport;
use App\Models\FinancialReportManualRow;
use App\Models\Statement;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Support\CommissionRates;
use Carbon\CarbonImmutable;
use Thinkycz\LaravelCore\Support\Typer;

/**
 * @phpstan-type FinancialRow array{id: string, kind: string, direction: string, source_type: string|null, source_key: string|null, label: string, occurred_on: string|null, calculated_amount: float, override_amount: float|null, effective_amount: float, note: string|null, details: array<string, mixed>, manual_row_id?: int}
 * @phpstan-type FinancialPayload array{income_rows: list<FinancialRow>, expense_rows: list<FinancialRow>, totals: array{income: float, expenses: float, profit: float}}
 */
class FinancialReportReadService
{
    /**
     * Revenue channel definitions and monthly commission rates.
     *
     * @var array<string, string>
     */
    private const array REVENUE_RATES = [
        'cash' => '0.00',
        'card' => CommissionRates::CARD,
        'bolt' => CommissionRates::BOLT,
        'wolt' => CommissionRates::WOLT,
        'foodora' => CommissionRates::FOODORA,
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

        $rows = $this->automaticRows($admin, $store, $year, $month);
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
     * Assemble current source rows before manual rows and overrides.
     *
     * @return list<FinancialRow>
     */
    public function automaticRows(User $admin, Store $store, int $year, int $month): array
    {
        $this->assertStore($admin, $store);

        return [
            ...$this->revenueRows($admin, $store, $year, $month),
            ...$this->stockRows($admin, $store, $year, $month),
            ...$this->wageRows($admin, $store, $year, $month),
            ...$this->recurringExpenseRows($admin, $store, $year, $month),
        ];
    }

    /**
     * Build recurring-expense management rows for the selected month.
     *
     * @return list<array<string, mixed>>
     */
    public function recurringExpenses(User $admin, Store $store, int $year, int $month): array
    {
        $this->assertStore($admin, $store);
        $period = new CarbonImmutable($year . '-' . $month . '-01');
        $query = FinancialRecurringExpense::query();
        FinancialRecurringExpense::scopeForUser($query, $admin);
        FinancialRecurringExpense::querySelect($query);
        $expenses = $query->where('store_id', $store->getKey())->with('versions')->orderBy('starts_on')->orderBy('id')->get();
        $rows = [];
        foreach ($expenses as $expense) {
            $status = $expense->getStartsOn() > $period->toDateString()
                ? 'upcoming'
                : ($expense->getEndsBefore() !== null && $expense->getEndsBefore() <= $period->toDateString() ? 'ended' : 'active');
            $reference = match ($status) {
                'upcoming' => new CarbonImmutable($expense->getStartsOn()),
                'ended' => (new CarbonImmutable($expense->getEndsBefore()))->subMonth(),
                default => $period,
            };
            $version = null;
            foreach ($expense->getVersions() as $candidate) {
                if ($candidate->getEffectiveFrom() <= $reference->toDateString()) {
                    $version = $candidate;
                }
            }
            if (!$version instanceof FinancialRecurringExpenseVersion) {
                continue;
            }
            $rows[] = [
                'id' => $expense->getKey(),
                'label' => $version->getLabel(),
                'amount' => $version->getAmount(),
                'due_day' => $version->getDueDay(),
                'note' => $version->getNote(),
                'starts_on' => \mb_substr($expense->getStartsOn(), 0, 7),
                'ends_before' => $expense->getEndsBefore() === null ? null : \mb_substr($expense->getEndsBefore(), 0, 7),
                'effective_from' => \mb_substr($version->getEffectiveFrom(), 0, 7),
                'status' => $status,
            ];
        }

        return $rows;
    }

    /**
     * Find an existing report.
     */
    public function findReport(User $admin, Store $store, int $year, int $month): FinancialReport|null
    {
        $this->assertStore($admin, $store);
        $query = FinancialReport::query();
        FinancialReport::scopeForUser($query, $admin);

        return $query->where('store_id', $store->getKey())->where('year', $year)->where('month', $month)->first();
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
        foreach (self::REVENUE_RATES as $channel => $decimalRate) {
            $rate = (float) $decimalRate;
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
        $query->with(['store', 'sourceStore']);

        return \array_values($query
            ->whereIn('type', [StockMovementTypeEnum::INCOMING->value, StockMovementTypeEnum::TRANSFER->value])
            ->whereNull('reversed_at')
            ->where('occurred_at', '>=', $start->utc())
            ->where('occurred_at', '<', $start->addMonth()->utc())
            ->orderBy('occurred_at')
            ->get()
            ->map(function (StockMovement $movement) use ($store): array {
                $details = [
                    'movement_id' => $movement->getKey(),
                    'movement_type' => $movement->getType()->value,
                    'destination_store_name' => $movement->getStore()?->getName() ?? $store->getName(),
                ];
                $sourceStore = $movement->getSourceStore();
                if ($sourceStore instanceof Store) {
                    $details['source_store_name'] = $sourceStore->getName();
                }

                return $this->automaticRow(
                    FinancialDirectionEnum::EXPENSE,
                    FinancialSourceTypeEnum::STOCK_MOVEMENT,
                    (string) $movement->getKey(),
                    $movement->getNumber(),
                    $movement->getOccurredAt()->setTimezone(AttendanceService::BUSINESS_TIMEZONE)->toDateString(),
                    \round(\abs($movement->getTotalValue()), 2),
                    $details,
                );
            })
            ->values()
            ->all());
    }

    /**
     * @return list<FinancialRow>
     */
    private function wageRows(User $admin, Store $store, int $year, int $month): array
    {
        $rows = [];
        $payroll = (new PayrollReportReadService())->build($admin, $store, $year, $month);
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
                    'minutes' => (int) \round(Typer::parseFloat($payslip['payable_hours'] ?? 0) * 60),
                    'base_amount' => Typer::parseFloat($payslip['base_amount'] ?? null),
                    'tip_amount' => Typer::parseFloat($payslip['tip_amount'] ?? null),
                    'deduction_amount' => Typer::parseFloat($payslip['deduction_amount'] ?? null),
                    'hourly_rate' => Typer::parseFloat($payslip['payable_hourly_rate'] ?? 0),
                    'wage_overridden' => Typer::assertBool($payslip['wage_overridden'] ?? false),
                ],
            );
        }

        return $rows;
    }

    /**
     * @return list<FinancialRow>
     */
    private function recurringExpenseRows(User $admin, Store $store, int $year, int $month): array
    {
        $period = new CarbonImmutable($year . '-' . $month . '-01');
        $query = FinancialRecurringExpense::query();
        FinancialRecurringExpense::scopeForUser($query, $admin);
        FinancialRecurringExpense::querySelect($query);
        $expenses = $query
            ->where('store_id', $store->getKey())
            ->whereDate('starts_on', '<=', $period->toDateString())
            ->where(static fn($activeQuery) => $activeQuery->whereNull('ends_before')->orWhereDate('ends_before', '>', $period->toDateString()))
            ->with('versions')
            ->orderBy('id')
            ->get();
        $rows = [];
        foreach ($expenses as $expense) {
            $version = null;
            foreach ($expense->getVersions() as $candidate) {
                if ($candidate->getEffectiveFrom() <= $period->toDateString()) {
                    $version = $candidate;
                }
            }
            if (!$version instanceof FinancialRecurringExpenseVersion) {
                continue;
            }
            $occurredOn = $period->setDay(\min($version->getDueDay(), $period->daysInMonth))->toDateString();
            $row = $this->automaticRow(
                FinancialDirectionEnum::EXPENSE,
                FinancialSourceTypeEnum::RECURRING_EXPENSE,
                (string) $expense->getKey(),
                $version->getLabel(),
                $occurredOn,
                $version->getAmount(),
                ['recurring_expense_id' => $expense->getKey(), 'due_day' => $version->getDueDay()],
            );
            $row['note'] = $version->getNote();
            $rows[] = $row;
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
     * Enforce company ownership and retail-only scope.
     */
    private function assertStore(User $admin, Store $store): void
    {
        if (!$admin->isAdmin() || $store->getUserId() !== $admin->getKey() || $store->isWarehouse()) {
            \abort(404);
        }
    }
}
