<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Report;

use App\Enums\StockMovementTypeEnum;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;
use Thinkycz\LaravelCore\Support\Typer;

class StatisticsController
{
    /**
     * Lower bound for the configurable period (days).
     */
    private const int MIN_PERIOD_DAYS = 7;

    /**
     * Upper bound for the configurable period (days).
     */
    private const int MAX_PERIOD_DAYS = 365;

    /**
     * Default period (days) when none is supplied.
     */
    private const int DEFAULT_PERIOD_DAYS = 30;

    /**
     * Render the per-store statistics page.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $userId = $user->getKey();

        $storesQuery = Store::query();
        Store::scopeForUser($storesQuery, $user);
        $stores = Store::querySelect($storesQuery)
            ->orderBy('name')
            ->get()
            ->all();

        $requestedStoreId = Typer::parseNullableInt($request->query('store_id'));
        $store = $this->resolveStore($user, $stores, $requestedStoreId);

        $periodDays = $this->resolvePeriodDays($request);
        $since = Carbon::now()->subDays($periodDays)->startOfDay();
        $sinceString = $since->toDateTimeString();

        $sales = $store instanceof Store
            ? $this->summariseSales($user, $store, $sinceString)
            : ['total' => 0.0, 'count' => 0, 'channels' => [], 'daily' => []];
        $incoming = $store instanceof Store
            ? $this->summariseMovement($user, $store, StockMovementTypeEnum::INCOMING, $sinceString)
            : ['quantity' => 0, 'value' => 0.0, 'movements' => 0];
        $outgoing = $store instanceof Store
            ? $this->summariseMovement($user, $store, StockMovementTypeEnum::OUTGOING, $sinceString)
            : ['quantity' => 0, 'value' => 0.0, 'movements' => 0];
        $inventoryValue = $store instanceof Store
            ? $this->inventoryValue($user, $store)
            : ['items' => 0, 'value' => 0.0];
        $topConsumed = $store instanceof Store
            ? $this->topConsumed($user, $store, $sinceString)
            : [];
        $dailySeries = $store instanceof Store
            ? $this->dailySeries($user, $store, $since, $periodDays)
            : [];

        return Inertia::render('reports/Statistics', [
            'store' => $store instanceof Store ? [
                'id' => $store->getKey(),
                'name' => $store->getName(),
            ] : null,
            'stores' => \array_map(static fn(Store $row): array => $row->toSelectOption(), $stores),
            'period_days' => $periodDays,
            'sales' => $sales,
            'incoming' => $incoming,
            'outgoing' => $outgoing,
            'current_inventory' => $inventoryValue,
            'top_consumed' => $topConsumed,
            'daily_series' => $dailySeries,
            'filters' => [
                'store_id' => $store?->getKey(),
                'period_days' => $periodDays,
            ],
        ]);
    }

    /**
     * Resolve the requested store, falling back to the first owned retail
     * store, then to the first owned store, then to null.
     *
     * @param array<int, Store> $stores
     */
    private function resolveStore(User $user, array $stores, int|null $requestedStoreId): Store|null
    {
        if ($requestedStoreId !== null) {
            $lookup = Store::query();
            Store::scopeForUser($lookup, $user);
            $match = $lookup->whereKey($requestedStoreId)->first();

            if ($match instanceof Store) {
                return $match;
            }
        }

        foreach ($stores as $store) {
            if (!$store->isWarehouse()) {
                return $store;
            }
        }

        return $stores[0] ?? null;
    }

    /**
     * Parse and clamp the period_days query parameter.
     */
    private function resolvePeriodDays(Request $request): int
    {
        $raw = Typer::parseNullableInt($request->query('period_days'));

        if ($raw === null) {
            return self::DEFAULT_PERIOD_DAYS;
        }

        if ($raw < self::MIN_PERIOD_DAYS) {
            return self::MIN_PERIOD_DAYS;
        }

        if ($raw > self::MAX_PERIOD_DAYS) {
            return self::MAX_PERIOD_DAYS;
        }

        return $raw;
    }

    /**
     * Summarise statement sales for the given store and period.
     *
     * @return array{
     *     total: float,
     *     count: int,
     *     channels: array<string, float>,
     *     daily: array<int, array{label: string, value: float}>
     * }
     */
    private function summariseSales(User $user, Store $store, string $since): array
    {
        $rows = DB::table('statement_days')
            ->join('statements', 'statements.id', '=', 'statement_days.statement_id')
            ->where('statements.user_id', $user->getKey())
            ->where('statements.store_id', $store->getKey())
            ->where('statement_days.date', '>=', $since)
            ->select(
                'statement_days.date',
                'statement_days.cash',
                'statement_days.card',
                'statement_days.wolt',
                'statement_days.bolt',
                'statement_days.bolt_cash',
                'statement_days.foodora',
                'statement_days.total',
            )
            ->orderBy('statement_days.date')
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
        $daily = [];
        $count = 0;

        foreach ($rows as $row) {
            $row = (array) $row;
            $totals['cash'] += Typer::parseFloat($row['cash']);
            $totals['card'] += Typer::parseFloat($row['card']);
            $totals['wolt'] += Typer::parseFloat($row['wolt']);
            $totals['bolt'] += Typer::parseFloat($row['bolt']);
            $totals['bolt_cash'] += Typer::parseFloat($row['bolt_cash']);
            $totals['foodora'] += Typer::parseFloat($row['foodora']);
            $totals['total_revenue'] += Typer::parseFloat($row['total']);
            ++$count;
            $daily[] = [
                'label' => \mb_substr(Typer::assertString($row['date']), -2),
                'value' => Typer::parseFloat($row['total']),
            ];
        }

        return [
            'total' => \round($totals['total_revenue'], 2),
            'count' => $count,
            'channels' => [
                'cash' => \round($totals['cash'], 2),
                'card' => \round($totals['card'], 2),
                'wolt' => \round($totals['wolt'], 2),
                'bolt' => \round($totals['bolt'], 2),
                'bolt_cash' => \round($totals['bolt_cash'], 2),
                'foodora' => \round($totals['foodora'], 2),
            ],
            'daily' => $daily,
        ];
    }

    /**
     * Summarise stock movements for a given type at the given store.
     *
     * @return array{quantity: int, value: float, movements: int}
     */
    private function summariseMovement(User $user, Store $store, StockMovementTypeEnum $type, string $since): array
    {
        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $user);
        StockMovement::scopeOfType($query, $type);
        StockMovement::scopeFromDate($query, $since);

        if ($type === StockMovementTypeEnum::INCOMING) {
            $query->where('store_id', $store->getKey());
        } else {
            $query->where('source_store_id', $store->getKey());
        }

        $builder = $query->getQuery();

        /** @var stdClass|null $row */
        $row = $builder
            ->selectRaw('COUNT(*) as movements_count, COALESCE(SUM(total_quantity), 0) as total_quantity, COALESCE(SUM(total_value), 0) as total_value')
            ->first();

        return [
            'quantity' => Typer::parseInt($row->total_quantity ?? null),
            'value' => Typer::parseFloat($row->total_value ?? null),
            'movements' => Typer::parseInt($row->movements_count ?? null),
        ];
    }

    /**
     * Sum the current inventory value for the selected store.
     *
     * @return array{items: int, value: float}
     */
    private function inventoryValue(User $user, Store $store): array
    {
        /** @var stdClass|null $row */
        $row = DB::table('store_items')
            ->join('items', 'items.id', '=', 'store_items.item_id')
            ->where('items.user_id', $user->getKey())
            ->where('store_items.store_id', $store->getKey())
            ->selectRaw('COUNT(*) as items_count, COALESCE(SUM(store_items.quantity * items.purchase_price), 0) as total_value')
            ->first();

        return [
            'items' => Typer::parseInt($row->items_count ?? null),
            'value' => Typer::parseFloat($row->total_value ?? null),
        ];
    }

    /**
     * Top consumed items at the store over the selected window.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topConsumed(User $user, Store $store, string $since): array
    {
        $movementQuery = StockMovement::query();
        StockMovement::scopeForUser($movementQuery, $user);
        StockMovement::scopeOfType($movementQuery, StockMovementTypeEnum::OUTGOING);
        StockMovement::scopeFromDate($movementQuery, $since);
        $movementQuery->where('source_store_id', $store->getKey());

        $subBuilder = $movementQuery->getQuery();

        $rows = DB::table('stock_movement_items')
            ->joinSub($subBuilder, 'movements', static function (\Illuminate\Database\Query\JoinClause $join): void {
                $join->on('movements.id', '=', 'stock_movement_items.stock_movement_id');
            })
            ->join('items', 'items.id', '=', 'stock_movement_items.item_id')
            ->where('items.user_id', $user->getKey())
            ->select(
                'items.id',
                'items.title',
                'items.sku',
                DB::raw('SUM(ABS(stock_movement_items.quantity_difference)) as total_quantity'),
                DB::raw('SUM(stock_movement_items.total) as total_value'),
                DB::raw('COUNT(*) as rows_count'),
            )
            ->groupBy('items.id', 'items.title', 'items.sku')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get();

        return $rows->map(static function (stdClass $row): array {
            $values = (array) $row;

            return [
                'item_id' => Typer::assertInt($values['id'] ?? null),
                'title' => Typer::assertString($values['title'] ?? null),
                'sku' => Typer::parseNullableString($values['sku'] ?? null),
                'total_quantity' => Typer::parseInt($values['total_quantity'] ?? null),
                'total_value' => Typer::parseFloat($values['total_value'] ?? null),
                'rows_count' => Typer::parseInt($values['rows_count'] ?? null),
            ];
        })->all();
    }

    /**
     * Daily time series for incoming and outgoing movements.
     *
     * @return array<int, array{label: string, incoming: float, outgoing: float}>
     */
    private function dailySeries(User $user, Store $store, Carbon $since, int $periodDays): array
    {
        $rows = DB::table('stock_movements')
            ->where('user_id', $user->getKey())
            ->where(function (QueryBuilder $query) use ($store): void {
                $query->where(static function (QueryBuilder $query) use ($store): void {
                    $query->where('type', StockMovementTypeEnum::INCOMING->value)
                        ->where('store_id', $store->getKey());
                })->orWhere(static function (QueryBuilder $query) use ($store): void {
                    $query->where('type', StockMovementTypeEnum::OUTGOING->value)
                        ->where('source_store_id', $store->getKey());
                });
            })
            ->where('created_at', '>=', $since->toDateTimeString())
            ->selectRaw('DATE(created_at) as day, type, SUM(total_value) as total_value')
            ->groupBy('day', 'type')
            ->get();

        $byDay = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $day = Typer::assertString($row['day']);
            if (!isset($byDay[$day])) {
                $byDay[$day] = ['incoming' => 0.0, 'outgoing' => 0.0];
            }
            $type = Typer::assertString($row['type']);
            $bucket = $type === StockMovementTypeEnum::INCOMING->value ? 'incoming' : 'outgoing';
            $byDay[$day][$bucket] = Typer::parseFloat($row['total_value']);
        }

        $series = [];
        for ($offset = $periodDays - 1; $offset >= 0; --$offset) {
            $date = Carbon::now()->subDays($offset)->toDateString();
            $bucket = $byDay[$date] ?? ['incoming' => 0.0, 'outgoing' => 0.0];
            $series[] = [
                'label' => \mb_substr($date, -2),
                'incoming' => \round($bucket['incoming'], 2),
                'outgoing' => \round($bucket['outgoing'], 2),
            ];
        }

        return $series;
    }
}
