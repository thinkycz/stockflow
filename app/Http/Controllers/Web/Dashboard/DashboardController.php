<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Dashboard;

use App\Enums\StockMovementTypeEnum;
use App\Models\InventorySession;
use App\Models\Statement;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Services\InventorySessionService;
use App\Support\ActiveStoreResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;
use Thinkycz\LaravelCore\Support\Typer;

class DashboardController
{
    /**
     * Render the dashboard for the currently active store.
     *
     * Every metric is scoped to the active store resolved via
     * `ActiveStoreResolver`. Without an active store the page returns
     * an empty payload and the frontend renders an explanatory empty
     * state.
     */
    public function __invoke(Request $request, InventorySessionService $inventoryService): Response
    {
        $user = User::mustAuth();
        $owner = $user->resolveScopeUser();
        $now = Carbon::now();
        $startOfToday = $now->copy()->startOfDay();
        $startOfTodayString = $startOfToday->toDateTimeString();
        $startOfMonthString = $now->copy()->startOfMonth()->toDateString();
        $thirtyDaysAgoString = $now->copy()->subDays(30)->toDateTimeString();

        $activeStore = ActiveStoreResolver::resolve($request, $user);

        if (!$activeStore instanceof Store) {
            return Inertia::render('Dashboard', $this->emptyPayload());
        }

        $storeId = $activeStore->getKey();

        $inventoryValue = (float) DB::table('store_items')
            ->join('items', 'items.id', '=', 'store_items.item_id')
            ->where('items.user_id', $owner->getKey())
            ->where('store_items.store_id', $storeId)
            ->sum(DB::raw('store_items.quantity * items.purchase_price'));

        $itemsInStore = StoreItem::query()->where('store_id', $storeId)->with('item')->get();

        $itemsCount = $itemsInStore->count();

        $stockStatus = [
            'in_stock' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
            'no_data' => 0,
        ];
        $lowStockCount = 0;

        foreach ($itemsInStore as $row) {
            $prediction = $inventoryService->predictedRunOut($activeStore, $row->getItem());

            if ($prediction['status'] === InventorySessionService::STATUS_OUT) {
                ++$stockStatus['out_of_stock'];
            } elseif ($prediction['status'] === InventorySessionService::STATUS_SOON) {
                ++$stockStatus['low_stock'];
                ++$lowStockCount;
            } elseif ($prediction['status'] === InventorySessionService::STATUS_NO_DATA) {
                ++$stockStatus['no_data'];
            } else {
                ++$stockStatus['in_stock'];
            }
        }

        $todayMovements = $this->countMovementsForStore($owner, $storeId, $startOfTodayString);

        $topConsumed = $this->topConsumed($owner, $storeId, $thirtyDaysAgoString);

        $recentMovements = StockMovement::query();
        StockMovement::scopeForUser($recentMovements, $owner);
        $recentMovements->where(static function (Builder $query) use ($storeId): void {
            $query
                ->where(static function (Builder $query) use ($storeId): void {
                    $query
                        ->where('type', StockMovementTypeEnum::INCOMING->value)
                        ->where('store_id', $storeId);
                })
                ->orWhere(static function (Builder $query) use ($storeId): void {
                    $query
                        ->where('type', StockMovementTypeEnum::TRANSFER->value)
                        ->where(static function (Builder $query) use ($storeId): void {
                            $query->where('source_store_id', $storeId)->orWhere('store_id', $storeId);
                        });
                })
                ->orWhere(static function (Builder $query) use ($storeId): void {
                    $query
                        ->whereIn('type', [StockMovementTypeEnum::ADJUSTMENT->value, StockMovementTypeEnum::CONSUMPTION->value, StockMovementTypeEnum::INVENTORY_RECONCILIATION->value])
                        ->where('store_id', $storeId);
                });
        });
        $recentMovements = $recentMovements
            ->with(['store', 'creator'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static fn(StockMovement $movement): array => [
                'id' => $movement->getKey(),
                'number' => $movement->getNumber(),
                'type' => $movement->getType()->value,
                'store_name' => $movement->getStore()?->getName(),
                'total_quantity' => $movement->getTotalQuantity(),
                'total_value' => $movement->getTotalValue(),
                'created_at' => $movement->getOccurredAt()->toDateTimeString(),
            ])
            ->all();

        $recentStatementsQuery = Statement::query();
        Statement::scopeForUser($recentStatementsQuery, $owner);
        Statement::scopeForStore($recentStatementsQuery, $storeId);
        $recentStatements = $recentStatementsQuery
            ->withSum('days as period_total', 'total')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(static fn(Statement $statement): array => [
                'id' => $statement->getKey(),
                'year' => $statement->getYear(),
                'month' => $statement->getMonth(),
                'total' => Typer::parseFloat($statement->getAttribute('period_total')),
            ])
            ->all();

        $monthIncomingValue = $this->monthValueForStore($owner, $storeId, StockMovementTypeEnum::INCOMING, $startOfMonthString, 'store_id');
        $monthOutgoingValue = $this->consumptionValueForStore($owner, $storeId, $startOfMonthString);
        $lastInventory = InventorySession::query()->where('user_id', $owner->getKey())->where('store_id', $storeId)->orderByDesc('counted_at')->first();

        return Inertia::render('Dashboard', [
            'active_store' => [
                'id' => $activeStore->getKey(),
                'name' => $activeStore->getName(),
            ],
            'metrics' => [
                'inventory_value' => $inventoryValue,
                'items_count' => $itemsCount,
                'low_stock_items' => $lowStockCount,
                'today_movements' => $todayMovements,
                'month_incoming' => $monthIncomingValue,
                'month_outgoing' => $monthOutgoingValue,
                'last_inventory_at' => $lastInventory?->getCountedAt()->toJSON(),
            ],
            'stock_status' => $stockStatus,
            'top_consumed' => $topConsumed,
            'recent_movements' => $recentMovements,
            'recent_statements' => $recentStatements,
            'can_manage_movements' => $user->isAdmin(),
        ]);
    }

    /**
     * Count stock movements touching the given store (incoming as
     * destination, outgoing as source, adjustment as either) created
     * on or after `$since`.
     */
    private function countMovementsForStore(User $user, int $storeId, string $since): int
    {
        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $user);
        StockMovement::scopeFromDate($query, $since);
        $query->where(static function (Builder $query) use ($storeId): void {
            $query
                ->where(static function (Builder $query) use ($storeId): void {
                    $query
                        ->where('type', StockMovementTypeEnum::INCOMING->value)
                        ->where('store_id', $storeId);
                })
                ->orWhere(static function (Builder $query) use ($storeId): void {
                    $query
                        ->where('type', StockMovementTypeEnum::TRANSFER->value)
                        ->where(static function (Builder $query) use ($storeId): void {
                            $query->where('source_store_id', $storeId)->orWhere('store_id', $storeId);
                        });
                })
                ->orWhere(static function (Builder $query) use ($storeId): void {
                    $query
                        ->whereIn('type', [StockMovementTypeEnum::ADJUSTMENT->value, StockMovementTypeEnum::CONSUMPTION->value, StockMovementTypeEnum::INVENTORY_RECONCILIATION->value])
                        ->where('store_id', $storeId);
                });
        });

        return $query->count();
    }

    /**
     * Sum total_value of the given movement type scoped to the active
     * store since the supplied boundary.
     */
    private function monthValueForStore(
        User $user,
        int $storeId,
        StockMovementTypeEnum $type,
        string $since,
        string $storeColumn,
    ): float {
        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $user);
        StockMovement::scopeOfType($query, $type);
        StockMovement::scopeFromDate($query, $since);

        return (float) $query
            ->where($storeColumn, $storeId)
            ->sum('total_value');
    }

    /**
     * Top 5 consumed items for the active store.
     *
     * @return array<int, array<string, mixed>>
     */
    private function topConsumed(User $user, int $storeId, string $since): array
    {
        $rows = DB::table('stock_movement_items')
            ->join('stock_movements as movements', 'movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->join('items', 'items.id', '=', 'stock_movement_items.item_id')
            ->where('items.user_id', $user->getKey())
            ->where('movements.user_id', $user->getKey())
            ->where('movements.store_id', $storeId)
            ->where('movements.occurred_at', '>=', $since)
            ->where('stock_movement_items.classification', 'consumption')
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
            ->limit(5)
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
     * Consumption value classified at line level.
     */
    private function consumptionValueForStore(User $user, int $storeId, string $since): float
    {
        return (float) DB::table('stock_movement_items')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->where('stock_movements.user_id', $user->getKey())
            ->where('stock_movements.store_id', $storeId)
            ->where('stock_movements.occurred_at', '>=', $since)
            ->where('stock_movement_items.classification', 'consumption')
            ->sum('stock_movement_items.total');
    }

    /**
     * Payload returned when the user has no active store. Numeric
     * metrics are zeroed out and lists are empty so the page can
     * render without errors.
     *
     * @return array<string, mixed>
     */
    private function emptyPayload(): array
    {
        return [
            'active_store' => null,
            'metrics' => [
                'inventory_value' => 0.0,
                'items_count' => 0,
                'low_stock_items' => 0,
                'today_movements' => 0,
                'month_incoming' => 0.0,
                'month_outgoing' => 0.0,
                'last_inventory_at' => null,
            ],
            'stock_status' => [
                'in_stock' => 0,
                'low_stock' => 0,
                'out_of_stock' => 0,
                'no_data' => 0,
            ],
            'top_consumed' => [],
            'recent_movements' => [],
            'recent_statements' => [],
            'can_manage_movements' => false,
        ];
    }
}
