<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Report;

use App\Enums\AdjustmentReasonEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\User;
use App\Services\StatementService;
use App\Support\ActiveStoreResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;
use Thinkycz\LaravelCore\Support\Typer;

class ReportController
{
    /**
     * Render the reports page.
     *
     * Every metric on this page is scoped to the currently active
     * store. Without an active store the page returns an empty payload
     * and the frontend renders an explanatory empty state.
     */
    public function __invoke(Request $request, StatementService $statementService): Response
    {
        $user = User::mustAuth();
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfMonthString = $startOfMonth->toDateString();

        $allTime = $request->query('all_time') === '1' || $request->query('period') === 'all';
        $year = $allTime ? null : (Typer::parseNullableInt($request->query('year')) ?? $now->year);
        $month = $allTime ? null : (Typer::parseNullableInt($request->query('month')) ?? $now->month);

        $activeStore = ActiveStoreResolver::resolve($request, $user);

        if (!$activeStore instanceof Store) {
            return Inertia::render('reports/Index', $this->emptyPayload($user, $statementService, $allTime, $year, $month));
        }

        $storeId = $activeStore->getKey();

        $statementReport = $statementService->buildReport($user, $storeId, $year, $month);

        $currentInventoryValue = (float) DB::table('store_items')
            ->join('items', 'items.id', '=', 'store_items.item_id')
            ->where('items.user_id', $user->getKey())
            ->where('store_items.store_id', $storeId)
            ->sum(DB::raw('store_items.quantity * items.purchase_price'));

        $incomingQuery = StockMovement::query();
        StockMovement::scopeForUser($incomingQuery, $user);
        StockMovement::scopeOfType($incomingQuery, StockMovementTypeEnum::INCOMING);
        $monthlyIncomingValue = (float) $incomingQuery
            ->where('store_id', $storeId)
            ->where('created_at', '>=', $startOfMonthString)
            ->sum('total_value');

        $outgoingQuery = StockMovement::query();
        StockMovement::scopeForUser($outgoingQuery, $user);
        StockMovement::scopeOfType($outgoingQuery, StockMovementTypeEnum::OUTGOING);
        $monthlyOutgoingValue = (float) $outgoingQuery
            ->where('source_store_id', $storeId)
            ->where('created_at', '>=', $startOfMonthString)
            ->sum('total_value');

        $mostMoved = DB::table('stock_movement_items')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->join('items', 'items.id', '=', 'stock_movement_items.item_id')
            ->where('stock_movements.user_id', $user->getKey())
            ->where(static function (QueryBuilder $query) use ($storeId): void {
                $query
                    ->where(static function (QueryBuilder $query) use ($storeId): void {
                        $query
                            ->where('stock_movements.type', StockMovementTypeEnum::INCOMING->value)
                            ->where('stock_movements.store_id', $storeId);
                    })
                    ->orWhere(static function (QueryBuilder $query) use ($storeId): void {
                        $query
                            ->where('stock_movements.type', StockMovementTypeEnum::OUTGOING->value)
                            ->where('stock_movements.source_store_id', $storeId);
                    });
            })
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
            ->get()
            ->map(static function (stdClass $row): array {
                $rowValues = (array) $row;

                return [
                    'item_id' => Typer::assertInt($rowValues['id'] ?? null),
                    'item_title' => Typer::assertString($rowValues['title'] ?? null),
                    'item_sku' => Typer::parseNullableString($rowValues['sku'] ?? null),
                    'total_quantity' => Typer::parseInt($rowValues['total_quantity'] ?? null),
                    'total_value' => Typer::parseFloat($rowValues['total_value'] ?? null),
                    'rows_count' => Typer::assertInt($rowValues['rows_count'] ?? null),
                ];
            })
            ->all();

        $adjustmentMovements = StockMovement::query();
        StockMovement::scopeForUser($adjustmentMovements, $user);
        $adjustmentMovements->where(static function (Builder $query) use ($storeId): void {
            $query
                ->where(static function (Builder $query) use ($storeId): void {
                    $query
                        ->where('type', StockMovementTypeEnum::INCOMING->value)
                        ->where('store_id', $storeId);
                })
                ->orWhere(static function (Builder $query) use ($storeId): void {
                    $query
                        ->where('type', StockMovementTypeEnum::OUTGOING->value)
                        ->where('source_store_id', $storeId);
                });
        });
        $adjustmentMovementIds = $adjustmentMovements->pluck('id')->all();

        $adjustments = StockMovementItem::query()
            ->whereIn('stock_movement_id', $adjustmentMovementIds)
            ->whereNotNull('adjustment_reason')
            ->select('adjustment_reason', DB::raw('COUNT(*) as rows_count'), DB::raw('SUM(ABS(quantity_difference)) as total_quantity'))
            ->groupBy('adjustment_reason')
            ->get()
            ->map(static function (StockMovementItem $row): array {
                return [
                    'reason' => $row->getAdjustmentReason()?->value,
                    'rows_count' => $row->getRowsCount(),
                    'total_quantity' => $row->getAggregatedTotalQuantity(),
                ];
            })
            ->all();

        return Inertia::render('reports/Index', [
            'active_store' => [
                'id' => $activeStore->getKey(),
                'name' => $activeStore->getName(),
            ],
            'inventory_value' => $currentInventoryValue,
            'monthly' => [
                'incoming' => $monthlyIncomingValue,
                'outgoing' => $monthlyOutgoingValue,
            ],
            'most_moved' => $mostMoved,
            'adjustments' => $adjustments,
            'reasons' => \array_map(
                static fn(AdjustmentReasonEnum $r): string => $r->value,
                AdjustmentReasonEnum::cases(),
            ),
            'statement_report' => $statementReport,
            'statement_filter' => [
                'all_time' => $allTime,
                'store_id' => $storeId,
                'year' => $year,
                'month' => $month,
            ],
        ]);
    }

    /**
     * Build the payload that the frontend renders when no store is
     * active. Numeric metrics are zeroed out and lists are empty so
     * the page is safe to render without errors.
     *
     * @return array<string, mixed>
     */
    private function emptyPayload(User $user, StatementService $statementService, bool $allTime, int|null $year, int|null $month): array
    {
        $statementReport = $statementService->buildReport($user, null, $year, $month);

        return [
            'active_store' => null,
            'inventory_value' => 0.0,
            'monthly' => [
                'incoming' => 0.0,
                'outgoing' => 0.0,
            ],
            'most_moved' => [],
            'adjustments' => [],
            'reasons' => \array_map(
                static fn(AdjustmentReasonEnum $r): string => $r->value,
                AdjustmentReasonEnum::cases(),
            ),
            'statement_report' => $statementReport,
            'statement_filter' => [
                'all_time' => $allTime,
                'store_id' => null,
                'year' => $year,
                'month' => $month,
            ],
        ];
    }
}
