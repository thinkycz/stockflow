<?php

declare(strict_types=1);

namespace App\Domain\BankStatements;

use App\Enums\BankStatementReconciliationStatusEnum;
use App\Enums\BankStatementStatusEnum;
use App\Enums\BankStatementTransactionCategoryEnum;
use App\Models\BankStatement;
use App\Models\BankStatementTransaction;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\Store;
use App\Models\User;
use App\Support\CommissionRates;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

class BankStatementReconciliationService
{
    /**
     * Maximum absolute difference considered a match.
     */
    private const string TOLERANCE = '5.00';

    /**
     * Reconcile one bank transaction with current daily statement data.
     *
     * @return array{status: string, actual: string, expected: string|null, difference: string|null, reason: string|null}
     */
    public function forTransaction(BankStatementTransaction $transaction): array
    {
        return $this->reconcile($transaction, $this->loadDays($transaction->getBankStatement(), [$transaction]));
    }

    /**
     * Build reconciliation rows and aggregate counts for one import.
     *
     * @return array{counts: array{matched: int, mismatch: int, unresolved: int, excluded: int}, rows: array<int, array<string, int|string|null>>}
     */
    public function forStatement(BankStatement $statement): array
    {
        $counts = ['matched' => 0, 'mismatch' => 0, 'unresolved' => 0, 'excluded' => 0];
        $rows = [];

        $transactions = $statement->getTransactions();
        $days = $this->loadDays($statement, $transactions);
        foreach ($transactions as $transaction) {
            $result = $this->reconcile($transaction, $days);
            match ($result['status']) {
                'matched' => ++$counts['matched'],
                'mismatch' => ++$counts['mismatch'],
                'unresolved' => ++$counts['unresolved'],
                'excluded' => ++$counts['excluded'],
                default => null,
            };
            $rows[] = ['transaction_id' => $transaction->getKey(), ...$result];
        }

        return ['counts' => $counts, 'rows' => $rows];
    }

    /**
     * Build the compact bank-control status for a statement month.
     *
     * @return array{statement_id: int|null, status: string, counts: array{matched: int, mismatch: int, unresolved: int, excluded: int}}
     */
    public function monthlyStatus(User $user, Store|null $store, int $year, int $month): array
    {
        $empty = ['matched' => 0, 'mismatch' => 0, 'unresolved' => 0, 'excluded' => 0];

        if (!$store instanceof Store) {
            return ['statement_id' => null, 'status' => 'not_uploaded', 'counts' => $empty];
        }

        $query = BankStatement::query();
        BankStatement::scopeForUser($query, $user->resolveScopeUser());
        BankStatement::scopeForStore($query, $store->getKey());
        BankStatement::scopeForMonth($query, $year, $month);
        $statement = $query->latest()->first();

        if (!$statement instanceof BankStatement) {
            return ['statement_id' => null, 'status' => 'not_uploaded', 'counts' => $empty];
        }

        $counts = $statement->getStatus() === BankStatementStatusEnum::CONFIRMED
            ? $this->forStatement($statement)['counts']
            : $empty;

        return [
            'statement_id' => $statement->getKey(),
            'status' => $statement->getStatus()->value,
            'counts' => $counts,
        ];
    }

    /**
     * Reconcile against the request-local indexed day projection.
     *
     * @param array<string, list<StatementDay>> $daysByDate
     *
     * @return array{status: string, actual: string, expected: string|null, difference: string|null, reason: string|null}
     */
    private function reconcile(BankStatementTransaction $transaction, array $daysByDate): array
    {
        $actual = $this->money($transaction->getAmount());
        $category = $transaction->getCategory();

        if (!$category->reconciliable()) {
            return $this->result(BankStatementReconciliationStatusEnum::EXCLUDED, $actual, null, null, null);
        }

        $from = $transaction->getSalesFrom();
        $to = $transaction->getSalesTo();

        if ($from === null || $to === null || $from->isAfter($to)) {
            return $this->result(BankStatementReconciliationStatusEnum::UNRESOLVED, $actual, null, null, 'missing_sales_period');
        }

        $days = [];
        foreach ($daysByDate as $date => $dateDays) {
            if ($date >= $from->toDateString() && $date <= $to->toDateString()) {
                $days = [...$days, ...$dateDays];
            }
        }
        $expectedDayCount = (int) CarbonImmutable::parse($from->toDateString())
            ->diffInDays(CarbonImmutable::parse($to->toDateString())) + 1;

        if ($expectedDayCount !== \count($days)) {
            return $this->result(BankStatementReconciliationStatusEnum::UNRESOLVED, $actual, null, null, 'missing_statement_days');
        }

        $card = BigDecimal::zero();
        $wolt = BigDecimal::zero();
        $bolt = BigDecimal::zero();
        $boltCash = BigDecimal::zero();
        $foodora = BigDecimal::zero();

        foreach ($days as $day) {
            $card = $card->plus($day->getCardDecimal());
            $wolt = $wolt->plus($day->getWoltDecimal());
            $bolt = $bolt->plus($day->getBoltDecimal());
            $boltCash = $boltCash->plus($day->getBoltCashDecimal());
            $foodora = $foodora->plus($day->getFoodoraDecimal());
        }

        $expected = match ($category) {
            BankStatementTransactionCategoryEnum::CARD => $card->multipliedBy(BigDecimal::one()->minus(CommissionRates::CARD)),
            BankStatementTransactionCategoryEnum::WOLT => $wolt->multipliedBy(BigDecimal::one()->minus(CommissionRates::WOLT)),
            BankStatementTransactionCategoryEnum::FOODORA => $foodora->multipliedBy(BigDecimal::one()->minus(CommissionRates::FOODORA)),
            BankStatementTransactionCategoryEnum::BOLT => $bolt->minus($bolt->plus($boltCash)->multipliedBy(CommissionRates::BOLT)),
            default => BigDecimal::zero(),
        };
        $expected = $expected->toScale(2, RoundingMode::HalfUp);
        $difference = $actual->minus($expected)->toScale(2, RoundingMode::HalfUp);
        $status = $difference->abs()->isLessThanOrEqualTo(self::TOLERANCE)
            ? BankStatementReconciliationStatusEnum::MATCHED
            : BankStatementReconciliationStatusEnum::MISMATCH;

        return $this->result($status, $actual, $expected, $difference, null);
    }

    /**
     * Fetch required days once per import without materializing parent statement IDs.
     *
     * @param iterable<BankStatementTransaction> $transactions
     *
     * @return array<string, list<StatementDay>>
     */
    private function loadDays(BankStatement $statement, iterable $transactions): array
    {
        $from = null;
        $to = null;
        foreach ($transactions as $transaction) {
            $start = $transaction->getSalesFrom();
            $end = $transaction->getSalesTo();
            if (!$transaction->getCategory()->reconciliable() || $start === null || $end === null || $start->isAfter($end)) {
                continue;
            }
            $from = $from === null ? $start->toDateString() : \min($from, $start->toDateString());
            $to = $to === null ? $end->toDateString() : \max($to, $end->toDateString());
        }
        if ($from === null || $to === null) {
            return [];
        }

        $parents = Statement::query()->select('id');
        Statement::scopeForUser($parents, $statement->getUserId());
        Statement::scopeForStore($parents, $statement->getStoreId());
        $days = StatementDay::query()->whereIn('statement_id', $parents)
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->orderBy('date')->get();
        $indexed = [];
        foreach ($days as $day) {
            $indexed[$day->getDate()][] = $day;
        }

        return $indexed;
    }

    /**
     * Normalize money to two decimal places.
     */
    private function money(string $value): BigDecimal
    {
        return BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * Serialize a reconciliation result.
     *
     * @return array{status: string, actual: string, expected: string|null, difference: string|null, reason: string|null}
     */
    private function result(
        BankStatementReconciliationStatusEnum $status,
        BigDecimal $actual,
        BigDecimal|null $expected,
        BigDecimal|null $difference,
        string|null $reason,
    ): array {
        return [
            'status' => $status->value,
            'actual' => (string) $actual,
            'expected' => $expected === null ? null : (string) $expected,
            'difference' => $difference === null ? null : (string) $difference,
            'reason' => $reason,
        ];
    }
}
