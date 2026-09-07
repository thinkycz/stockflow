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

/**
 * @phpstan-type Check array{status: string, actual: string, expected: string|null, difference: string|null, reason: string|null, pairing: string, amount_check: string, tolerance: string|null}
 * @phpstan-type Candidate array{from: string, to: string, expected: string|null, difference: string|null, tolerance: string|null, within_tolerance: bool, reason: string|null, source: string}
 * @phpstan-type Discovery array{candidates: list<Candidate>, automatic: Candidate|null, reason: string|null}
 * @phpstan-type Payment array{transaction_id: int, statement_id: int, channel: string, booked_on: string, from: string, to: string, state: string, check: Check}
 * @phpstan-type Row array{transaction_id: int, status: string, actual: string, expected: string|null, difference: string|null, reason: string|null, pairing: string, amount_check: string, tolerance: string|null, candidates: list<Candidate>, automatic: Candidate|null, discovery_reason: string|null}
 */
class BankStatementReconciliationService
{
    /**
     * Maximum absolute difference considered a match.
     */
    private const string TOLERANCE = '5.00';

    /**
     * Reconcile one bank transaction with current daily statement data.
     *
     * @return Check
     */
    public function forTransaction(BankStatementTransaction $transaction): array
    {
        return $this->assignedCheck($transaction, $this->loadDays($transaction->getBankStatement(), [$transaction]), $this->reservations($transaction->getBankStatement(), [$transaction], $transaction->getBankStatement()->getStatus() === BankStatementStatusEnum::CONFIRMED));
    }

    /**
     * Build reconciliation rows and aggregate counts for one import.
     *
     * @return array{counts: array{matched: int, mismatch: int, unresolved: int, excluded: int}, rows: list<Row>, paired_count: int}
     */
    public function forStatement(BankStatement $statement): array
    {
        $counts = ['matched' => 0, 'mismatch' => 0, 'unresolved' => 0, 'excluded' => 0];
        $rows = [];

        $transactions = $statement->getTransactions();
        $days = $this->loadDays($statement, $transactions);
        $reservations = $this->reservations($statement, \array_values($transactions->all()), $statement->getStatus() === BankStatementStatusEnum::CONFIRMED);
        $discoveries = $statement->getStatus() === BankStatementStatusEnum::REVIEW
            ? $this->discoverPeriods(\array_values($transactions->all()), $days, $reservations) : [];
        $pairedCount = 0;
        foreach ($transactions as $transaction) {
            $result = $this->assignedCheck($transaction, $days, $reservations);
            match ($result['status']) {
                'matched' => ++$counts['matched'],
                'mismatch' => ++$counts['mismatch'],
                'unresolved' => ++$counts['unresolved'],
                'excluded' => ++$counts['excluded'],
                default => null,
            };
            if ($result['pairing'] === 'paired') {
                ++$pairedCount;
            }
            $discovery = $discoveries[$transaction->getKey()] ?? ['candidates' => [], 'automatic' => null, 'reason' => null];
            $rows[] = ['transaction_id' => $transaction->getKey(), ...$result,
                'candidates' => $discovery['candidates'], 'automatic' => $discovery['automatic'], 'discovery_reason' => $discovery['reason']];
        }

        return ['counts' => $counts, 'rows' => $rows, 'paired_count' => $pairedCount];
    }

    /**
     * Project all confirmed receipts touching this sales month; never persist paid flags.
     *
     * @return array{statement_id: int|null, status: string, paired_count: int, counts: array{matched: int, mismatch: int, unresolved: int, excluded: int}, cells: array<string, array<string, list<Payment>>>}
     */
    public function monthlyStatus(User $user, Store|null $store, int $year, int $month): array
    {
        $result = ['statement_id' => null, 'status' => 'not_uploaded', 'paired_count' => 0,
            'counts' => ['matched' => 0, 'mismatch' => 0, 'unresolved' => 0, 'excluded' => 0], 'cells' => []];
        if (!$store instanceof Store) {
            return $result;
        }
        $start = CarbonImmutable::parse(\sprintf('%04d-%02d-01', $year, $month))->startOfDay();
        $end = $start->endOfMonth();
        $parents = BankStatement::query()->select('id')->where('status', BankStatementStatusEnum::CONFIRMED->value);
        BankStatement::scopeForUser($parents, $user->resolveScopeUser());
        BankStatement::scopeForStore($parents, $store->getKey());
        $transactions = BankStatementTransaction::query()->with('bankStatement')->whereIn('bank_statement_id', $parents)
            ->whereIn('category', ['card', 'wolt', 'bolt', 'foodora'])->where('amount', '>', 0)
            ->whereDate('sales_from', '<=', $end->toDateString())->whereDate('sales_to', '>=', $start->toDateString())
            ->orderBy('booked_on')->orderBy('id')->get();
        $first = $transactions->first();
        if (!$first instanceof BankStatementTransaction) {
            $query = BankStatement::query();
            BankStatement::scopeForUser($query, $user->resolveScopeUser());
            BankStatement::scopeForStore($query, $store->getKey());
            BankStatement::scopeForMonth($query, $year, $month);
            $latest = $query->latest()->first();
            if ($latest instanceof BankStatement) {
                $result['statement_id'] = $latest->getKey();
                $result['status'] = $latest->getStatus()->value;
            }

            return $result;
        }
        $days = $this->loadDays($first->getBankStatement(), $transactions);
        $reservations = $this->reservations($first->getBankStatement(), \array_values($transactions->all()), true);
        $result['status'] = 'confirmed';
        foreach ($transactions as $transaction) {
            $from = $transaction->getSalesFrom();
            $to = $transaction->getSalesTo();
            if ($from === null || $to === null || $from->isAfter($to)) {
                continue;
            }
            $check = $this->assignedCheck($transaction, $days, $reservations);
            if ($check['status'] === 'matched') {
                ++$result['counts']['matched'];
            } elseif ($check['status'] === 'mismatch') {
                ++$result['counts']['mismatch'];
            } else {
                ++$result['counts']['unresolved'];
            }
            if ($check['pairing'] === 'paired') {
                ++$result['paired_count'];
            }
            $result['statement_id'] = $transaction->getBankStatement()->getKey();
            $payment = ['transaction_id' => $transaction->getKey(), 'statement_id' => $result['statement_id'],
                'channel' => $transaction->getCategory()->value, 'booked_on' => $transaction->getBookedOn()->toDateString(),
                'from' => $from->toDateString(), 'to' => $to->toDateString(),
                'state' => $check['pairing'] === 'paired' && $check['amount_check'] === 'within_tolerance' ? 'verified' : 'review', 'check' => $check];
            for ($date = \max($from->toDateString(), $start->toDateString()); $date <= \min($to->toDateString(), $end->toDateString()); $date = CarbonImmutable::parse($date)->addDay()->toDateString()) {
                $result['cells'][$date][$transaction->getCategory()->value][] = $payment;
            }
        }

        return $result;
    }

    /**
     * Preserve the amount calculation while flagging conflicting assigned periods.
     *
     * @param array<string, list<StatementDay>> $days
     * @param list<BankStatementTransaction> $reservations
     *
     * @return Check
     */
    private function assignedCheck(BankStatementTransaction $transaction, array $days, array $reservations): array
    {
        $check = $this->reconcile($transaction, $days);
        $from = $transaction->getSalesFrom()?->toDateString();
        $to = $transaction->getSalesTo()?->toDateString();
        if (!$transaction->getCategory()->reconciliable() || $from === null || $to === null) {
            return $check;
        }
        foreach ($reservations as $other) {
            $otherFrom = $other->getSalesFrom()?->toDateString();
            $otherTo = $other->getSalesTo()?->toDateString();
            if ($other->getKey() === $transaction->getKey() || $other->getCategory() !== $transaction->getCategory() || $otherFrom === null || $otherTo === null) {
                continue;
            }
            if ($from <= $otherTo && $otherFrom <= $to) {
                $check['status'] = 'unresolved';
                $check['pairing'] = 'unresolved';
                $check['reason'] = 'period_conflict';
                break;
            }
        }

        return $check;
    }

    /**
     * Reconcile against the request-local indexed day projection.
     *
     * @param array<string, list<StatementDay>> $daysByDate
     *
     * @return Check
     */
    private function reconcile(BankStatementTransaction $transaction, array $daysByDate, string|null $start = null, string|null $end = null): array
    {
        $actual = $this->money($transaction->getAmount());
        $category = $transaction->getCategory();

        if (!$category->reconciliable()) {
            return $this->result(BankStatementReconciliationStatusEnum::EXCLUDED, $actual, null, null, null);
        }

        $from = $start === null ? $transaction->getSalesFrom() : CarbonImmutable::parse($start);
        $to = $end === null ? $transaction->getSalesTo() : CarbonImmutable::parse($end);

        if ($from === null || $to === null || $from->isAfter($to)) {
            return $this->result(BankStatementReconciliationStatusEnum::UNRESOLVED, $actual, null, null, 'missing_sales_period');
        }

        $days = [];
        foreach ($daysByDate as $date => $dateDays) {
            if ($date < $from->toDateString() || $date > $to->toDateString()) {
                continue;
            }
            if (\count($dateDays) !== 1) {
                return $this->result(BankStatementReconciliationStatusEnum::UNRESOLVED, $actual, null, null, 'duplicate_statement_days');
            }
            $days[] = $dateDays[0];
        }
        $expectedDayCount = (int) CarbonImmutable::parse($from->toDateString())->diffInDays(CarbonImmutable::parse($to->toDateString())) + 1;
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
        $status = $difference->abs()->isLessThanOrEqualTo($this->tolerance($category, $expected))
            ? BankStatementReconciliationStatusEnum::MATCHED
            : BankStatementReconciliationStatusEnum::MISMATCH;

        return $this->result($status, $actual, $expected, $difference, null, $this->tolerance($category, $expected));
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
            if ($this->needsDiscovery($transaction)) {
                $start = $transaction->getBookedOn()->copy()->subDays(45);
                $end = $transaction->getBookedOn()->copy()->subDay();
                $explicit = $this->explicitPeriod($transaction);
                if ($explicit !== null) {
                    $start = CarbonImmutable::parse($explicit['from']);
                    $end = CarbonImmutable::parse($explicit['to']);
                }
            }
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
     * Keep fee estimates separate from the accepted amount tolerance.
     */
    private function tolerance(BankStatementTransactionCategoryEnum $category, BigDecimal $expected): BigDecimal
    {
        $minimum = BigDecimal::of(self::TOLERANCE);
        if ($category === BankStatementTransactionCategoryEnum::FOODORA) {
            return $minimum;
        }

        return BigDecimal::max($minimum, $expected->abs()->multipliedBy('0.0125')->toScale(2, RoundingMode::HalfUp));
    }

    /**
     * Only entirely missing marketplace periods receive suggestions.
     */
    private function needsDiscovery(BankStatementTransaction $transaction): bool
    {
        return \in_array($transaction->getCategory(), [BankStatementTransactionCategoryEnum::WOLT, BankStatementTransactionCategoryEnum::BOLT], true) &&
            $transaction->getSalesFrom() === null && $transaction->getSalesTo() === null;
    }

    /**
     * Recognize labelled date ranges as data, never execute document instructions.
     * Both dates must include the year; unlabelled identifiers are not date evidence.
     *
     * @return array{from: string, to: string}|null
     */
    private function explicitPeriod(BankStatementTransaction $transaction): array|null
    {
        $date = '(?:\\d{4}-\\d{2}-\\d{2}|\\d{1,2}\\.\\s*\\d{1,2}\\.\\s*\\d{4})';
        $text = \implode(' ', [$transaction->getDescription() ?? '', $transaction->getVariableSymbol() ?? '', $transaction->getSpecificSymbol() ?? '']);
        \preg_match_all('/(?:settlement(?:\\s+period)?|sales\\s+period|payout\\s+period|obdob[ií]|obdobie|z[uú]čtovac[ií]\\s+obdob[ií])\\s*:?\\s*(?:od|from)?\\s*(' . $date . ')\\s*(?:–|—|-|až|do|to)\\s*(' . $date . ')/iu', $text, $matches, \PREG_SET_ORDER);
        if (\count($matches) !== 1) {
            return null;
        }
        $from = $this->strictDate($matches[0][1]);
        $to = $this->strictDate($matches[0][2]);
        if ($from === null || $to === null || $from > $to || $to >= $transaction->getBookedOn()->toDateString()) {
            return null;
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * Reject impossible dates rather than normalizing them to another day.
     */
    private function strictDate(string $value): string|null
    {
        if (\preg_match('/^(\\d{4})-(\\d{2})-(\\d{2})$/', $value, $parts) === 1) {
            [$year, $month, $day] = [(int) $parts[1], (int) $parts[2], (int) $parts[3]];
        } elseif (\preg_match('/^(\\d{1,2})\\.\\s*(\\d{1,2})\\.\\s*(\\d{4})$/', $value, $parts) === 1) {
            [$year, $month, $day] = [(int) $parts[3], (int) $parts[2], (int) $parts[1]];
        } else {
            return null;
        }

        return \checkdate($month, $day, $year) ? \sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    /**
     * Load already assigned periods across imports without per-transaction queries.
     *
     * @param list<BankStatementTransaction> $transactions
     *
     * @return list<BankStatementTransaction>
     */
    private function reservations(BankStatement $statement, array $transactions, bool $confirmedOnly = false): array
    {
        if ($transactions === []) {
            return $transactions;
        }
        $parents = BankStatement::query()->select('id');
        if ($confirmedOnly) {
            $parents->where('status', BankStatementStatusEnum::CONFIRMED->value);
        }
        BankStatement::scopeForUser($parents, $statement->getUserId());
        BankStatement::scopeForStore($parents, $statement->getStoreId());

        return [...$transactions, ...BankStatementTransaction::query()->whereIn('bank_statement_id', $parents)
            ->whereIn('category', ['card', 'wolt', 'bolt', 'foodora'])->where('amount', '>', 0)->whereNotNull('sales_from')->whereNotNull('sales_to')->get()->all()];
    }

    /**
     * Evaluate all plausible candidates before choosing any automatic draft period.
     *
     * @param list<BankStatementTransaction> $transactions
     * @param array<string, list<StatementDay>> $days
     * @param list<BankStatementTransaction> $reservations
     *
     * @return array<int, Discovery>
     */
    private function discoverPeriods(array $transactions, array $days, array $reservations): array
    {
        $all = [];
        foreach ($transactions as $transaction) {
            if (!$this->needsDiscovery($transaction)) {
                continue;
            }
            $candidates = [];
            $calendar = $this->calendarPeriod($transaction);
            $explicit = $this->explicitPeriod($transaction);
            if ($explicit !== null) {
                $candidates[] = $this->candidate($transaction, $days, $explicit['from'], $explicit['to'], 'explicit');
            } else {
                $booked = CarbonImmutable::parse($transaction->getBookedOn()->toDateString());
                for ($from = $booked->subDays(45); $from->isBefore($booked); $from = $from->addDay()) {
                    for ($length = 1; $length <= 14; ++$length) {
                        $to = $from->addDays($length - 1);
                        if (!$to->isBefore($booked)) {
                            break;
                        }
                        $source = $this->isCalendarPeriod($transaction->getCategory(), $from, $to) ? 'calendar' : 'inferred';
                        $candidate = $this->candidate($transaction, $days, $from->toDateString(), $to->toDateString(), $source);
                        if ($candidate['expected'] !== null && BigDecimal::of($candidate['expected'])->isPositive()) {
                            $candidates[] = $candidate;
                        }
                    }
                }
            }
            if ($explicit === null && $calendar !== null && \array_filter($candidates, static fn(array $candidate): bool => $candidate['from'] === $calendar['from'] && $candidate['to'] === $calendar['to']) === []) {
                $candidates[] = $this->candidate($transaction, $days, $calendar['from'], $calendar['to'], 'calendar');
            }
            foreach ($candidates as &$candidate) {
                foreach ($reservations as $reserved) {
                    $from = $reserved->getSalesFrom()?->toDateString();
                    $to = $reserved->getSalesTo()?->toDateString();
                    if ($reserved->getKey() === $transaction->getKey() || $reserved->getCategory() !== $transaction->getCategory() || $from === null || $to === null) {
                        continue;
                    }
                    if ($this->periodsConflict($transaction, $candidate, $reserved, ['from' => $from, 'to' => $to])) {
                        $candidate['reason'] = 'period_conflict';
                        break;
                    }
                }
            }
            unset($candidate);
            \usort($candidates, static function (array $a, array $b) use ($calendar): int {
                $priorityA = $calendar !== null && $a['from'] === $calendar['from'] && $a['to'] === $calendar['to'];
                $priorityB = $calendar !== null && $b['from'] === $calendar['from'] && $b['to'] === $calendar['to'];
                if ($priorityA !== $priorityB) {
                    return $priorityB <=> $priorityA;
                }
                if (($a['difference'] === null) !== ($b['difference'] === null)) {
                    return ($a['difference'] === null) <=> ($b['difference'] === null);
                }
                $difference = BigDecimal::of($a['difference'] ?? '0')->abs()->compareTo(BigDecimal::of($b['difference'] ?? '0')->abs());
                if ($difference !== 0) {
                    return $difference;
                }
                $delay = \strcmp($b['to'], $a['to']);

                return $delay !== 0 ? $delay : \strcmp($a['from'], $b['from']);
            });
            $all[$transaction->getKey()] = $candidates;
        }

        $result = [];
        foreach ($transactions as $transaction) {
            if (!isset($all[$transaction->getKey()])) {
                continue;
            }
            $candidates = $all[$transaction->getKey()];
            $eligible = \array_values(\array_filter($candidates, $this->eligibleCandidate(...)));
            $automatic = \count($eligible) === 1 ? $eligible[0] : null;
            $reason = $eligible === [] ? 'no_matching_period' : 'ambiguous_period';
            if ($automatic !== null) {
                $reason = null;
                foreach ($transactions as $other) {
                    if ($other->getKey() === $transaction->getKey() || $other->getCategory() !== $transaction->getCategory()) {
                        continue;
                    }
                    $otherCalendar = $this->calendarPeriod($other);
                    foreach ($all[$other->getKey()] ?? [] as $competing) {
                        $calendarSuggestion = $otherCalendar !== null && $competing['reason'] === null && $competing['from'] === $otherCalendar['from'] && $competing['to'] === $otherCalendar['to'];
                        if (($this->eligibleCandidate($competing) || $calendarSuggestion) && $this->periodsConflict($transaction, $automatic, $other, $competing)) {
                            $automatic = null;
                            $reason = 'period_conflict';
                            break 2;
                        }
                    }
                }
            }
            if ($eligible === [] && $candidates !== []) {
                $reason = $candidates[0]['reason'] ?? $reason;
            }
            $calendar = $this->calendarPeriod($transaction);
            if ($automatic !== null && $automatic['source'] !== 'explicit' && $calendar !== null &&
                ($automatic['from'] !== $calendar['from'] || $automatic['to'] !== $calendar['to'])) {
                $automatic = null;
                $reason = 'calendar_conflict';
            }
            if ($transaction->isManuallyEdited()) {
                $automatic = null;
                $reason = 'manual_period';
            }
            $result[$transaction->getKey()] = ['candidates' => \array_slice($candidates, 0, 3), 'automatic' => $automatic, 'reason' => $reason];
        }

        return $result;
    }

    /**
     * Compare an explicit or searched range using the same payout contract.
     *
     * @param array<string, list<StatementDay>> $days
     *
     * @return Candidate
     */
    private function candidate(BankStatementTransaction $transaction, array $days, string $from, string $to, string $source): array
    {
        $check = $this->reconcile($transaction, $days, $from, $to);

        return ['from' => $from, 'to' => $to, 'expected' => $check['expected'], 'difference' => $check['difference'],
            'tolerance' => $check['tolerance'], 'within_tolerance' => $check['status'] === 'matched', 'reason' => $check['reason'], 'source' => $source];
    }

    /**
     * Automatic draft choices require a successful amount check even for explicit dates.
     *
     * @param Candidate $candidate
     */
    private function eligibleCandidate(array $candidate): bool
    {
        return $candidate['reason'] === null && $candidate['within_tolerance'] && $candidate['expected'] !== null && BigDecimal::of($candidate['expected'])->isPositive();
    }

    /**
     * Latest completed provider calendar period, offered only within seven days.
     * This is a suggestion, not evidence of the provider's actual settlement.
     *
     * @return array{from: string, to: string}|null
     */
    private function calendarPeriod(BankStatementTransaction $transaction): array|null
    {
        $booked = CarbonImmutable::parse($transaction->getBookedOn()->toDateString());
        for ($delay = 1; $delay <= 7; ++$delay) {
            $to = $booked->subDays($delay);
            $from = $transaction->getCategory() === BankStatementTransactionCategoryEnum::BOLT
                ? $to->subDays(6)
                : $to->startOfMonth()->addDays($to->day > 25 ? 25 : $to->day - 5);
            if ($this->isCalendarPeriod($transaction->getCategory(), $from, $to)) {
                return ['from' => $from->toDateString(), 'to' => $to->toDateString()];
            }
        }

        return null;
    }

    /**
     * Recognize inclusive weekly Bolt and six-times-monthly Wolt boundaries.
     */
    private function isCalendarPeriod(BankStatementTransactionCategoryEnum $category, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        if ($category === BankStatementTransactionCategoryEnum::BOLT) {
            return $from->isMonday() && $to->isSunday() && $from->diffInDays($to) === 6.0;
        }

        return $category === BankStatementTransactionCategoryEnum::WOLT && $from->format('Y-m') === $to->format('Y-m') &&
            (($from->day === 26 && $to->isLastOfMonth()) ||
                (\in_array($from->day, [1, 6, 11, 16, 21], true) && $to->day === $from->day + 4));
    }

    /**
     * Prevent overlapping sales and inferred periods that reverse payout order.
     *
     * @param array{from: string, to: string, ...} $a
     * @param array{from: string, to: string, ...} $b
     */
    private function periodsConflict(BankStatementTransaction $first, array $a, BankStatementTransaction $second, array $b): bool
    {
        return ($a['from'] <= $b['to'] && $b['from'] <= $a['to']) ||
            ($first->getBookedOn()->isBefore($second->getBookedOn()) && $a['to'] >= $b['from']) ||
            ($first->getBookedOn()->isAfter($second->getBookedOn()) && $a['from'] <= $b['to']);
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
     * @return Check
     */
    private function result(
        BankStatementReconciliationStatusEnum $status,
        BigDecimal $actual,
        BigDecimal|null $expected,
        BigDecimal|null $difference,
        string|null $reason,
        BigDecimal|null $tolerance = null,
    ): array {
        return [
            'status' => $status->value,
            'pairing' => $expected !== null ? 'paired' : ($status === BankStatementReconciliationStatusEnum::EXCLUDED ? 'excluded' : 'unresolved'),
            'amount_check' => $expected === null ? 'not_checked' : ($status === BankStatementReconciliationStatusEnum::MATCHED ? 'within_tolerance' : 'difference'),
            'tolerance' => $tolerance === null ? null : (string) $tolerance,
            'actual' => (string) $actual,
            'expected' => $expected === null ? null : (string) $expected,
            'difference' => $difference === null ? null : (string) $difference,
            'reason' => $reason,
        ];
    }
}
