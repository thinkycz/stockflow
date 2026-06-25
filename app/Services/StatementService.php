<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementTypeEnum;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class StatementService
{
    /**
     * Provision rate charged on card payments.
     */
    public const float CARD_PROVISION_RATE = 0.01;

    /**
     * Provision rate charged on marketplace channels (Bolt, Bolt Cash,
     * Wolt, Foodora). Only pure cash is exempt.
     */
    public const float MARKETPLACE_PROVISION_RATE = 0.30;

    /**
     * Find an existing statement for the given store/month, or create a new one
     * with one row per day of the month.
     */
    public function findOrCreateForMonth(User $user, Store $store, int $year, int $month): Statement
    {
        $query = Statement::query();
        Statement::scopeForUser($query, $user);
        Statement::scopeForStore($query, $store->getKey());
        Statement::scopeForMonth($query, $year, $month);

        $statement = $query->first();

        if ($statement instanceof Statement) {
            return $statement;
        }

        return DB::transaction(function () use ($user, $store, $year, $month): Statement {
            $statement = Statement::query()->create([
                'user_id' => $user->getKey(),
                'store_id' => $store->getKey(),
                'year' => $year,
                'month' => $month,
            ]);

            $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
            $rows = [];
            for ($day = 1; $day <= $daysInMonth; ++$day) {
                $rows[] = [
                    'statement_id' => $statement->getKey(),
                    'date' => Carbon::createFromDate($year, $month, $day)->toDateString(),
                    'cash' => 0,
                    'card' => 0,
                    'wolt' => 0,
                    'bolt' => 0,
                    'bolt_cash' => 0,
                    'foodora' => 0,
                    'total' => 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }

            StatementDay::query()->insert($rows);

            return $statement->fresh(['days']) ?? $statement;
        });
    }

    /**
     * Update all daily amounts on the statement in one transaction.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function updateDays(Statement $statement, array $rows): void
    {
        DB::transaction(function () use ($statement, $rows): void {
            $existing = $statement->days()->get()->keyBy(static fn(StatementDay $day): string => $day->getDate());
            $seen = [];

            foreach ($rows as $row) {
                $row = Typer::assertArray($row);
                $date = Typer::assertString($row['date'] ?? '');
                $day = $existing->get($date);

                if (!$day instanceof StatementDay) {
                    $this->fail(['days' => \__('Date :date does not belong to this statement.', ['date' => $date])]);
                }

                $seen[$date] = true;
                $cash = Typer::parseFloat($row['cash'] ?? 0);
                $card = Typer::parseFloat($row['card'] ?? 0);
                $wolt = Typer::parseFloat($row['wolt'] ?? 0);
                $bolt = Typer::parseFloat($row['bolt'] ?? 0);
                $boltCash = Typer::parseFloat($row['bolt_cash'] ?? 0);
                $foodora = Typer::parseFloat($row['foodora'] ?? 0);

                $day->update([
                    'cash' => $cash,
                    'card' => $card,
                    'wolt' => $wolt,
                    'bolt' => $bolt,
                    'bolt_cash' => $boltCash,
                    'foodora' => $foodora,
                    'total' => \round($cash + $card + $wolt + $bolt + $boltCash + $foodora, 2),
                ]);
            }
        });
    }

    /**
     * Reset all daily amounts to zero without deleting the statement.
     */
    public function clear(Statement $statement): void
    {
        DB::transaction(function () use ($statement): void {
            $statement->days()->update([
                'cash' => 0,
                'card' => 0,
                'wolt' => 0,
                'bolt' => 0,
                'bolt_cash' => 0,
                'foodora' => 0,
                'total' => 0,
            ]);
        });
    }

    /**
     * Build a report rollup for the given period and optional store.
     * When `$storeId` is null, aggregates across all stores owned by
     * the user. When `$year`/`$month` are null, aggregates across all
     * time. The result contains totals, channel breakdown and a daily
     * revenue series suitable for the line chart.
     *
     * @return array{
     *     period: array<string, mixed>,
     *     totals: array<string, float|int>,
     *     channels: array<string, float>,
     *     daily: array<int, array{label: string, value: float}>,
     *     days_with_revenue: int,
     * }
     */
    public function buildReport(
        User $user,
        int|null $storeId,
        int|null $year,
        int|null $month,
    ): array {
        $query = StatementDay::query();
        $query->whereHas('statement', static function ($statementQuery) use ($user, $storeId, $year, $month): void {
            $statementQuery->where('user_id', $user->getKey());
            if ($storeId !== null) {
                $statementQuery->where('store_id', $storeId);
            }
            if ($year !== null) {
                $statementQuery->where('year', $year);
            }
            if ($month !== null) {
                $statementQuery->where('month', $month);
            }
        });

        $rows = $query
            ->orderBy('date')
            ->get();

        $totals = [
            'cash' => 0.0,
            'card' => 0.0,
            'wolt' => 0.0,
            'bolt' => 0.0,
            'bolt_cash' => 0.0,
            'foodora' => 0.0,
            'total_revenue' => 0.0,
        ];
        $daysWithRevenue = 0;
        $daily = [];

        foreach ($rows as $row) {
            $totals['cash'] += $row->getCash();
            $totals['card'] += $row->getCard();
            $totals['wolt'] += $row->getWolt();
            $totals['bolt'] += $row->getBolt();
            $totals['bolt_cash'] += $row->getBoltCash();
            $totals['foodora'] += $row->getFoodora();
            $totals['total_revenue'] += $row->getTotal();
            $daily[] = [
                'label' => \mb_substr($row->getDate(), -2),
                'value' => $row->getTotal(),
            ];
            if ($row->getTotal() > 0) {
                ++$daysWithRevenue;
            }
        }

        $investment = $this->calculateReportInvestment($user, $storeId, $year, $month);
        $cardProvision = \round($totals['card'] * self::CARD_PROVISION_RATE, 2);
        $marketplaceProvision = \round(
            ($totals['wolt'] + $totals['bolt'] + $totals['bolt_cash'] + $totals['foodora']) * self::MARKETPLACE_PROVISION_RATE,
            2,
        );
        $provisions = \round($cardProvision + $marketplaceProvision, 2);
        $grossMargin = \round($totals['total_revenue'] - $investment - $provisions, 2);
        $marginPercent = $totals['total_revenue'] > 0 ? \round(($grossMargin / $totals['total_revenue']) * 100, 2) : 0.0;
        $dailyAverage = $daysWithRevenue > 0 ? \round($totals['total_revenue'] / $daysWithRevenue, 2) : 0.0;

        return [
            'period' => [
                'store_id' => $storeId,
                'year' => $year,
                'month' => $month,
            ],
            'totals' => [
                'total_revenue' => \round($totals['total_revenue'], 2),
                'investment' => \round($investment, 2),
                'card_provision' => $cardProvision,
                'marketplace_provision' => $marketplaceProvision,
                'provisions' => $provisions,
                'gross_margin' => $grossMargin,
                'margin_percent' => $marginPercent,
                'daily_average' => $dailyAverage,
            ],
            'channels' => [
                'cash' => \round($totals['cash'], 2),
                'card' => \round($totals['card'], 2),
                'wolt' => \round($totals['wolt'], 2),
                'bolt' => \round($totals['bolt'], 2),
                'bolt_cash' => \round($totals['bolt_cash'], 2),
                'foodora' => \round($totals['foodora'], 2),
            ],
            'daily' => $daily,
            'days_with_revenue' => $daysWithRevenue,
        ];
    }

    /**
     * Calculate the investment (cost of goods leaving the store) for the
     * statement's store and month, summing `total` across all OUTGOING
     * stock movements where the store is the source.
     */
    public function calculateInvestment(Statement $statement): float
    {
        $start = Carbon::createFromDate($statement->getYear(), $statement->getMonth(), 1)->startOfMonth();
        $end = Carbon::createFromDate($statement->getYear(), $statement->getMonth(), 1)->endOfMonth();

        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $statement->getUserId());
        StockMovement::scopeOfType($query, StockMovementTypeEnum::OUTGOING);
        $query->where('source_store_id', $statement->getStoreId());
        StockMovement::scopeFromDate($query, $start->toDateTimeString());
        StockMovement::scopeUntilDate($query, $end->toDateTimeString());

        $rows = $query
            ->withSum('movementItems as investment_total', 'total')
            ->get();

        return Typer::parseFloat($rows->sum(static fn(StockMovement $m): float => Typer::parseFloat($m->getAttribute('investment_total'))));
    }

    /**
     * Build a metrics array for the statement, including total revenue,
     * investment, gross margin, margin percent, daily average and channel
     * shares.
     *
     * @param iterable<StatementDay> $days
     *
     * @return array<string, mixed>
     */
    public function buildMetrics(Statement $statement, iterable $days, float $investment): array
    {
        $totalRevenue = 0.0;
        $cashTotal = 0.0;
        $cardTotal = 0.0;
        $woltTotal = 0.0;
        $boltTotal = 0.0;
        $boltCashTotal = 0.0;
        $foodoraTotal = 0.0;
        $daysWithRevenue = 0;

        foreach ($days as $day) {
            $total = $day->getTotal();
            $totalRevenue += $total;
            $cashTotal += $day->getCash();
            $cardTotal += $day->getCard();
            $woltTotal += $day->getWolt();
            $boltTotal += $day->getBolt();
            $boltCashTotal += $day->getBoltCash();
            $foodoraTotal += $day->getFoodora();
            if ($total > 0) {
                ++$daysWithRevenue;
            }
        }

        $totalRevenue = \round($totalRevenue, 2);
        $investment = \round($investment, 2);
        $cardProvision = \round($cardTotal * self::CARD_PROVISION_RATE, 2);
        $marketplaceProvision = \round(
            ($woltTotal + $boltTotal + $boltCashTotal + $foodoraTotal) * self::MARKETPLACE_PROVISION_RATE,
            2,
        );
        $provisions = \round($cardProvision + $marketplaceProvision, 2);
        $grossMargin = \round($totalRevenue - $investment - $provisions, 2);
        $marginPercent = $totalRevenue > 0 ? \round(($grossMargin / $totalRevenue) * 100, 2) : 0.0;
        $dailyAverage = $daysWithRevenue > 0 ? \round($totalRevenue / $daysWithRevenue, 2) : 0.0;

        return [
            'total_revenue' => $totalRevenue,
            'investment' => $investment,
            'card_provision' => $cardProvision,
            'marketplace_provision' => $marketplaceProvision,
            'provisions' => $provisions,
            'gross_margin' => $grossMargin,
            'margin_percent' => $marginPercent,
            'daily_average' => $dailyAverage,
            'channels' => [
                'cash' => \round($cashTotal, 2),
                'card' => \round($cardTotal, 2),
                'wolt' => \round($woltTotal, 2),
                'bolt' => \round($boltTotal, 2),
                'bolt_cash' => \round($boltCashTotal, 2),
                'foodora' => \round($foodoraTotal, 2),
            ],
        ];
    }

    /**
     * Sum stock movement totals for outgoing movements whose source
     * store matches the filter. Used by `buildReport()` to compute the
     * cost of goods leaving the selected scope.
     */
    private function calculateReportInvestment(
        User $user,
        int|null $storeId,
        int|null $year,
        int|null $month,
    ): float {
        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $user);
        StockMovement::scopeOfType($query, StockMovementTypeEnum::OUTGOING);

        if ($storeId !== null) {
            $query->where('source_store_id', $storeId);
        }
        if ($year !== null && $month !== null) {
            $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $end = Carbon::createFromDate($year, $month, 1)->endOfMonth();
            StockMovement::scopeFromDate($query, $start->toDateTimeString());
            StockMovement::scopeUntilDate($query, $end->toDateTimeString());
        }

        $rows = $query
            ->withSum('movementItems as investment_total', 'total')
            ->get();

        return Typer::parseFloat($rows->sum(static fn(StockMovement $m): float => Typer::parseFloat($m->getAttribute('investment_total'))));
    }

    /**
     * @param array<string, array<array-key, mixed>|string> $messages
     */
    private function fail(array $messages): never
    {
        $validator = Resolver::resolveValidatorFactory()->make([], []);
        $thrower = new Thrower($validator);

        foreach ($messages as $key => $message) {
            $thrower->message($key, $message);
        }

        $thrower->throw();
    }
}
