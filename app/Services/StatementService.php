<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationalActivityTypeEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\Statement;
use App\Models\StatementDay;
use App\Models\StatementVersion;
use App\Models\StatementVersionDay;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class StatementService
{
    /**
     * Business timezone used for daily statements.
     */
    public const string TIMEZONE = 'Europe/Prague';

    /**
     * Provision rate charged on card payments.
     */
    public const float CARD_PROVISION_RATE = 0.01;

    /**
     * Provision rate charged on Bolt and Bolt Cash revenue.
     */
    public const float BOLT_PROVISION_RATE = 0.35;

    /**
     * Provision rate charged on Wolt revenue.
     */
    public const float WOLT_PROVISION_RATE = 0.30;

    /**
     * Provision rate charged on Foodora revenue.
     */
    public const float FOODORA_PROVISION_RATE = 0.30;

    /**
     * Upper bound for the number of versions shown in history lists.
     */
    public const int HISTORY_LIMIT = 200;

    /**
     * Find an existing statement for the given store/month, or create a new one
     * with one row per day of the month.
     */
    public function findOrCreateForMonth(User $user, Store $store, int $year, int $month): Statement
    {
        return DB::transaction(function () use ($user, $store, $year, $month): Statement {
            $store = Typer::assertInstance(
                Store::query()
                    ->where('user_id', $user->getKey())
                    ->whereKey($store->getKey())
                    ->lockForUpdate()
                    ->firstOrFail(),
                Store::class,
            );
            if (!$store->isActive()) {
                \abort(404);
            }

            $query = Statement::query();
            Statement::scopeForUser($query, $user);
            Statement::scopeForStore($query, $store->getKey());
            Statement::scopeForMonth($query, $year, $month);
            $statement = $query->lockForUpdate()->first();

            if ($statement instanceof Statement) {
                return $statement;
            }

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
                    'cash_checked' => false,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }

            StatementDay::query()->insert($rows);

            $this->snapshotLocked($statement, $user);

            return $statement->fresh(['days']) ?? $statement;
        });
    }

    /**
     * Update all daily amounts on the statement in one transaction and
     * record an immutable version snapshot afterwards so the previous
     * state can be restored from history.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function updateDays(Statement $statement, array $rows, User $user): void
    {
        DB::transaction(function () use ($statement, $rows, $user): void {
            $statement = $this->lockActiveStatement($statement);
            $this->updateDaysLocked($statement, $rows, $user);
        });
    }

    /**
     * Save statement rows and close all eligible attendances atomically.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function updateDaysAndCloseAttendances(Statement $statement, array $rows, User $user): void
    {
        DB::transaction(function () use ($statement, $rows, $user): void {
            $statement = $this->lockActiveStatement($statement);
            $this->updateDaysLocked($statement, $rows, $user);
            (new AttendanceService())->closeActiveCurrentDayAttendances($user, $statement->getStore());
        });
    }

    /**
     * Reset all daily amounts to zero without deleting the statement and
     * record an immutable version snapshot afterwards so the previous
     * state can be restored from history.
     */
    public function clear(Statement $statement, User $user): void
    {
        DB::transaction(function () use ($statement, $user): void {
            $statement = $this->lockActiveStatement($statement);
            $statement->days()->update([
                'cash' => 0,
                'card' => 0,
                'wolt' => 0,
                'bolt' => 0,
                'bolt_cash' => 0,
                'foodora' => 0,
                'total' => 0,
                'cash_checked' => false,
            ]);

            $this->snapshotLocked($statement, $user);
            $this->notify($statement, $user, OperationalActivityTypeEnum::STATEMENT_CLEARED);
        });
    }

    /**
     * Capture an immutable snapshot of the statement's daily rows. Used
     * after every successful save (update or clear) so the user can
     * restore the previous state later. Also called by `restoreVersion`
     * before overwriting the data, so the user can revert the restore
     * itself.
     */
    public function snapshot(Statement $statement, User $user): StatementVersion
    {
        return DB::transaction(function () use ($statement, $user): StatementVersion {
            $statement = $this->lockActiveStatement($statement);

            return $this->snapshotLocked($statement, $user);
        });
    }

    /**
     * Restore the statement's daily amounts from the given version. A
     * backup snapshot of the current state is taken first so the user
     * can revert the restore itself if it was a mistake.
     */
    public function restoreVersion(StatementVersion $version, User $user): void
    {
        DB::transaction(function () use ($version, $user): void {
            $statement = $this->lockActiveStatement($version->getStatement());
            $version = Typer::assertInstance(
                StatementVersion::query()
                    ->where('statement_id', $statement->getKey())
                    ->whereKey($version->getKey())
                    ->lockForUpdate()
                    ->firstOrFail(),
                StatementVersion::class,
            );

            $this->snapshotLocked($statement, $user);

            $existing = $statement->days()->lockForUpdate()->get()->keyBy(static fn(StatementDay $day): string => $day->getDate());

            foreach ($version->days()->orderBy('date')->get() as $versionDay) {
                $day = $existing->get($versionDay->getDate());

                if (!$day instanceof StatementDay) {
                    continue;
                }

                $day->update([
                    'cash' => $versionDay->getCash(),
                    'card' => $versionDay->getCard(),
                    'wolt' => $versionDay->getWolt(),
                    'bolt' => $versionDay->getBolt(),
                    'bolt_cash' => $versionDay->getBoltCash(),
                    'foodora' => $versionDay->getFoodora(),
                    'total' => $versionDay->getTotal(),
                    'cash_checked' => $versionDay->getCashChecked(),
                ]);
            }

            $this->notify($statement, $user, OperationalActivityTypeEnum::STATEMENT_RESTORED);
        });
    }

    /**
     * Return the version history for the given statement, newest first.
     * Each row exposes `created_by_email` for display purposes.
     *
     * @return array<int, array{
     *     id: int,
     *     snapshot_at: string,
     *     note: string|null,
     *     created_by: int|null,
     *     created_by_email: string|null,
     *     day_count: int,
     * }>
     */
    public function historyForStatement(Statement $statement, int $limit): array
    {
        $query = StatementVersion::query();
        StatementVersion::scopeForUser($query, $statement->getUserId());
        StatementVersion::scopeForStatement($query, $statement);
        $query->withCount('days');
        $query->orderByDesc('snapshot_at');
        $query->orderByDesc('id');
        $query->limit($limit);

        /** @var \Illuminate\Database\Eloquent\Collection<int, StatementVersion> $versions */
        $versions = $query->get();

        $creators = User::query()
            ->whereIn('id', $versions->pluck('created_by')->filter()->unique()->values()->all())
            ->get()
            ->keyBy(static fn(User $user): int => $user->getKey());

        $rows = [];
        foreach ($versions as $version) {
            $creatorId = $version->getCreatedBy();
            $creator = $creatorId !== null ? $creators->get($creatorId) : null;

            $rows[] = [
                'id' => $version->getKey(),
                'snapshot_at' => $version->getSnapshotAt()->toIso8601String(),
                'note' => $version->getNote(),
                'created_by' => $creatorId,
                'created_by_email' => $creator instanceof User ? $creator->getEmail() : null,
                'day_count' => Typer::assertInt($version->getAttribute('days_count')),
            ];
        }

        return $rows;
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
     *     inventory_coverage: array{average_days: float, covered_items: int, last_inventory_at: string|null},
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
        $marketplaceProvision = $this->marketplaceProvision($totals['wolt'], $totals['bolt'], $totals['bolt_cash'], $totals['foodora']);
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
            'inventory_coverage' => $this->inventoryCoverage($user, $storeId, $year, $month),
        ];
    }

    /**
     * Calculate estimated consumption cost for the statement month.
     */
    public function calculateInvestment(Statement $statement): float
    {
        $start = Carbon::createFromDate($statement->getYear(), $statement->getMonth(), 1)->startOfMonth();
        $end = Carbon::createFromDate($statement->getYear(), $statement->getMonth(), 1)->endOfMonth();

        return $this->consumptionCost(
            $statement->getUserId(),
            $statement->getStoreId(),
            $start,
            $end,
        );
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
        $marketplaceProvision = $this->marketplaceProvision($woltTotal, $boltTotal, $boltCashTotal, $foodoraTotal);
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
     * Update statement rows while the owning active store and statement are locked.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function updateDaysLocked(Statement $statement, array $rows, User $user): void
    {
        $existing = $statement->days()->lockForUpdate()->get()->keyBy(static fn(StatementDay $day): string => $day->getDate());

        foreach ($rows as $row) {
            $row = Typer::assertArray($row);
            $date = Typer::assertString($row['date'] ?? '');
            $day = $existing->get($date);

            if (!$day instanceof StatementDay) {
                continue;
            }

            $cash = Typer::parseFloat($row['cash'] ?? 0);
            $card = Typer::parseFloat($row['card'] ?? 0);
            $wolt = Typer::parseFloat($row['wolt'] ?? 0);
            $bolt = Typer::parseFloat($row['bolt'] ?? 0);
            $boltCash = Typer::parseFloat($row['bolt_cash'] ?? 0);
            $foodora = Typer::parseFloat($row['foodora'] ?? 0);

            $update = [
                'cash' => $cash,
                'card' => $card,
                'wolt' => $wolt,
                'bolt' => $bolt,
                'bolt_cash' => $boltCash,
                'foodora' => $foodora,
                'total' => \round($cash + $card + $wolt + $bolt + $boltCash + $foodora, 2),
            ];

            if ($user->isAdmin()) {
                $update['cash_checked'] = (bool) ($row['cash_checked'] ?? false);
            }

            $day->update($update);
        }

        $this->snapshotLocked($statement, $user);
        $this->notify($statement, $user, OperationalActivityTypeEnum::STATEMENT_SAVED);
    }

    /**
     * Capture a snapshot after the caller has acquired the statement lock.
     */
    private function snapshotLocked(Statement $statement, User $user): StatementVersion
    {
        $version = StatementVersion::query()->create([
            'user_id' => $statement->getUserId(),
            'statement_id' => $statement->getKey(),
            'created_by' => $user->getKey(),
            'snapshot_at' => Carbon::now(),
            'note' => null,
        ]);

        $rows = [];
        $now = Carbon::now();

        foreach ($statement->days()->orderBy('date')->get() as $day) {
            $rows[] = [
                'version_id' => $version->getKey(),
                'date' => $day->getDate(),
                'cash' => $day->getCash(),
                'card' => $day->getCard(),
                'wolt' => $day->getWolt(),
                'bolt' => $day->getBolt(),
                'bolt_cash' => $day->getBoltCash(),
                'foodora' => $day->getFoodora(),
                'total' => $day->getTotal(),
                'cash_checked' => $day->getCashChecked(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            StatementVersionDay::query()->insert($rows);
        }

        return $version->fresh(['days']) ?? $version;
    }

    /**
     * Lock the store before its statement and reject writes to inactive history.
     */
    private function lockActiveStatement(Statement $statement): Statement
    {
        $store = Typer::assertInstance(
            Store::query()
                ->where('user_id', $statement->getUserId())
                ->whereKey($statement->getStoreId())
                ->lockForUpdate()
                ->firstOrFail(),
            Store::class,
        );
        if (!$store->isActive()) {
            \abort(404);
        }

        return Typer::assertInstance(
            Statement::query()
                ->where('user_id', $statement->getUserId())
                ->where('store_id', $store->getKey())
                ->whereKey($statement->getKey())
                ->lockForUpdate()
                ->firstOrFail(),
            Statement::class,
        );
    }

    /**
     * Dispatch one committed statement activity.
     */
    private function notify(Statement $statement, User $user, OperationalActivityTypeEnum $type): void
    {
        $facts = [
            'Slack statement period' => \sprintf('%02d/%d', $statement->getMonth(), $statement->getYear()),
        ];
        $today = Carbon::now(self::TIMEZONE);
        $todayDay = $statement->days()->whereDate('date', $today->toDateString())->first();

        if ($todayDay instanceof StatementDay) {
            $facts += [
                'Slack statement date' => $today->format('j. n. Y'),
                'Slack statement cash' => $this->formatCurrency($todayDay->getCash()),
                'Slack statement card' => $this->formatCurrency($todayDay->getCard()),
                'Slack statement wolt' => $this->formatCurrency($todayDay->getWolt()),
                'Slack statement bolt' => $this->formatCurrency($todayDay->getBolt()),
                'Slack statement bolt cash' => $this->formatCurrency($todayDay->getBoltCash()),
                'Slack statement foodora' => $this->formatCurrency($todayDay->getFoodora()),
                'Slack statement today total' => $this->formatCurrency($todayDay->getTotal()),
            ];
        }

        OperationalActivityService::dispatch(
            $type,
            $user,
            Carbon::now('UTC')->toIso8601String(),
            Resolver::resolveUrlGenerator()->route('statements.index', [
                'store_id' => $statement->getStoreId(),
                'year' => $statement->getYear(),
                'month' => $statement->getMonth(),
            ]),
            [['store' => $statement->getStore(), 'perspective' => null]],
            $facts,
        );
    }

    /**
     * Format one statement amount for operational notifications.
     */
    private function formatCurrency(float $amount): string
    {
        return \number_format($amount, 2, ',', ' ') . ' Kč';
    }

    /**
     * Calculate consumption cost for the selected report scope.
     */
    private function calculateReportInvestment(
        User $user,
        int|null $storeId,
        int|null $year,
        int|null $month,
    ): float {
        $start = null;
        $end = null;
        if ($year !== null && $month !== null) {
            $start = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $end = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        }

        return $this->consumptionCost($user->getKey(), $storeId, $start, $end);
    }

    /**
     * Sum manual consumption and prorated inventory-derived consumption.
     */
    private function consumptionCost(
        int $userId,
        int|null $storeId,
        Carbon|null $periodStart,
        Carbon|null $periodEnd,
    ): float {
        $query = DB::table('stock_movement_items')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->where('stock_movements.user_id', $userId)
            ->where(static function (QueryBuilder $query): void {
                $query->where('stock_movements.type', StockMovementTypeEnum::CONSUMPTION->value)
                    ->orWhere(static function (QueryBuilder $query): void {
                        $query->where('stock_movements.type', StockMovementTypeEnum::INVENTORY_RECONCILIATION->value)
                            ->where('stock_movement_items.classification', 'consumption');
                    });
            });

        if ($periodEnd instanceof Carbon) {
            $query->where(static function (QueryBuilder $query) use ($periodEnd): void {
                $query->whereNull('stock_movements.reversed_at')
                    ->orWhere('stock_movements.reversed_at', '>', $periodEnd->toDateTimeString());
            });
        } else {
            $query->whereNull('stock_movements.reversed_at');
        }

        if ($storeId !== null) {
            $query->where('stock_movements.store_id', $storeId);
        }

        $rows = $query->get([
            'stock_movements.type',
            'stock_movements.occurred_at',
            'stock_movement_items.observation_started_at',
            'stock_movement_items.total',
        ]);

        $total = 0.0;
        foreach ($rows as $row) {
            $value = Typer::parseFloat($row->total);
            $occurredAt = Carbon::parse(Typer::assertString($row->occurred_at));
            $observationStartedAt = Typer::parseNullableString($row->observation_started_at);

            if ($periodStart === null || $periodEnd === null) {
                $total += $value;

                continue;
            }

            if ($observationStartedAt === null) {
                if ($occurredAt->betweenIncluded($periodStart, $periodEnd)) {
                    $total += $value;
                }

                continue;
            }

            $intervalStart = Carbon::parse($observationStartedAt);
            $overlapStart = $intervalStart->greaterThan($periodStart) ? $intervalStart : $periodStart;
            $overlapEnd = $occurredAt->lessThan($periodEnd) ? $occurredAt : $periodEnd;
            if ($overlapStart->greaterThanOrEqualTo($overlapEnd)) {
                continue;
            }

            $intervalSeconds = $intervalStart->diffInSeconds($occurredAt);
            if ($intervalSeconds <= 0) {
                continue;
            }
            $total += $value * ($overlapStart->diffInSeconds($overlapEnd) / $intervalSeconds);
        }

        return \round($total, 2);
    }

    /**
     * Describe the physical-count coverage behind estimated consumption.
     *
     * @return array{average_days: float, covered_items: int, last_inventory_at: string|null}
     */
    private function inventoryCoverage(User $user, int|null $storeId, int|null $year, int|null $month): array
    {
        if ($storeId === null) {
            return ['average_days' => 0.0, 'covered_items' => 0, 'last_inventory_at' => null];
        }

        $periodStart = $year !== null && $month !== null
            ? Carbon::createFromDate($year, $month, 1)->startOfMonth()
            : null;
        $periodEnd = $year !== null && $month !== null
            ? Carbon::createFromDate($year, $month, 1)->endOfMonth()
            : null;
        $rows = DB::table('inventory_session_items')
            ->join('inventory_sessions', 'inventory_sessions.id', '=', 'inventory_session_items.session_id')
            ->where('inventory_sessions.user_id', $user->getKey())
            ->where('inventory_sessions.store_id', $storeId)
            ->where('inventory_sessions.status', 'closed')
            ->whereNotNull('inventory_session_items.observation_started_at')
            ->get([
                'inventory_session_items.item_id',
                'inventory_session_items.observation_started_at',
                'inventory_sessions.counted_at',
            ]);

        $secondsByItem = [];
        foreach ($rows as $row) {
            $start = Carbon::parse(Typer::assertString($row->observation_started_at));
            $end = Carbon::parse(Typer::assertString($row->counted_at));
            if ($periodStart instanceof Carbon && $start->lessThan($periodStart)) {
                $start = $periodStart->copy();
            }
            if ($periodEnd instanceof Carbon && $end->greaterThan($periodEnd)) {
                $end = $periodEnd->copy();
            }
            if ($start->greaterThanOrEqualTo($end)) {
                continue;
            }
            $itemId = Typer::parseInt($row->item_id);
            $secondsByItem[$itemId] = ($secondsByItem[$itemId] ?? 0) + (int) $start->diffInSeconds($end);
        }

        $lastInventory = DB::table('inventory_sessions')
            ->where('user_id', $user->getKey())
            ->where('store_id', $storeId)
            ->where('status', 'closed')
            ->max('counted_at');

        return [
            'average_days' => $secondsByItem === [] ? 0.0 : \round((\array_sum($secondsByItem) / \count($secondsByItem)) / 86400, 1),
            'covered_items' => \count($secondsByItem),
            'last_inventory_at' => Typer::parseNullableString($lastInventory),
        ];
    }

    /**
     * Calculate marketplace provisions using each platform's contracted rate.
     */
    private function marketplaceProvision(float $wolt, float $bolt, float $boltCash, float $foodora): float
    {
        return \round(
            $wolt * self::WOLT_PROVISION_RATE
                + ($bolt + $boltCash) * self::BOLT_PROVISION_RATE
                + $foodora * self::FOODORA_PROVISION_RATE,
            2,
        );
    }
}
