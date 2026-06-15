<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Report;

use App\Enums\AdjustmentReasonEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
     */
    public function __invoke(): Response
    {
        $user = User::mustAuth();
        $startOfMonth = Carbon::now()->startOfMonth();
        $userId = $user->getKey();

        $currentInventoryValue = (float) DB::table('store_items')
            ->join('items', 'items.id', '=', 'store_items.item_id')
            ->where('items.user_id', $userId)
            ->sum(DB::raw('store_items.quantity * items.purchase_price'));

        $incomingQuery = StockMovement::query();
        StockMovement::scopeForUser($incomingQuery, $user);
        StockMovement::scopeOfType($incomingQuery, StockMovementTypeEnum::INCOMING);
        $monthlyIncomingValue = (float) $incomingQuery
            ->where('created_at', '>=', $startOfMonth->toDateString())
            ->sum('total_value');

        $outgoingQuery = StockMovement::query();
        StockMovement::scopeForUser($outgoingQuery, $user);
        StockMovement::scopeOfType($outgoingQuery, StockMovementTypeEnum::OUTGOING);
        $monthlyOutgoingValue = (float) $outgoingQuery
            ->where('created_at', '>=', $startOfMonth->toDateString())
            ->sum('total_value');

        $startOfMonthString = $startOfMonth->toDateString();

        $storeConsumptionQuery = Store::query();
        Store::scopeForUser($storeConsumptionQuery, $user);
        Store::scopeRetail($storeConsumptionQuery);
        $stores = Store::querySelect($storeConsumptionQuery)->get();

        $storeIds = $stores->pluck('id')->all();

        /** @var array<int, stdClass> $consumptionRows */
        $consumptionRows = $storeIds === []
            ? []
            : DB::table('stock_movements')
                ->whereIn('source_store_id', $storeIds)
                ->where('type', StockMovementTypeEnum::OUTGOING->value)
                ->where('created_at', '>=', $startOfMonthString)
                ->selectRaw('source_store_id, COUNT(*) as movements_count, SUM(total_quantity) as total_quantity, SUM(total_value) as total_value')
                ->groupBy('source_store_id')
                ->get()
                ->keyBy('source_store_id')
                ->all();

        $storeConsumption = $stores
            ->map(function (Store $store) use ($consumptionRows): array {
                $rowValues = (array) ($consumptionRows[$store->getKey()] ?? null);

                return [
                    'store_id' => $store->getKey(),
                    'store_name' => $store->getName(),
                    'movements_count' => Typer::parseInt($rowValues['movements_count'] ?? null),
                    'total_quantity' => Typer::parseInt($rowValues['total_quantity'] ?? null),
                    'total_value' => Typer::parseFloat($rowValues['total_value'] ?? null),
                ];
            })
            ->sortByDesc(static fn(array $row): float => $row['total_value'])
            ->values()
            ->all();

        $mostMoved = DB::table('stock_movement_items')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->join('items', 'items.id', '=', 'stock_movement_items.item_id')
            ->where('stock_movements.user_id', $userId)
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
                    'item_sku' => Typer::assertNullableString($rowValues['sku'] ?? null),
                    'total_quantity' => Typer::parseInt($rowValues['total_quantity'] ?? null),
                    'total_value' => Typer::parseFloat($rowValues['total_value'] ?? null),
                    'rows_count' => Typer::assertInt($rowValues['rows_count'] ?? null),
                ];
            })
            ->all();

        $adjustments = StockMovementItem::query()
            ->whereHas('stockMovement', static function (Builder $query) use ($user): void {
                $query->where('user_id', $user->getKey());
            })
            ->whereNotNull('adjustment_reason')
            ->select('adjustment_reason', DB::raw('COUNT(*) as rows_count'), DB::raw('SUM(ABS(quantity_difference)) as total_quantity'))
            ->groupBy('adjustment_reason')
            ->get()
            ->map(static fn(StockMovementItem $row): array => [
                'reason' => $row->getAdjustmentReason()?->value,
                'rows_count' => $row->getRowsCount(),
                'total_quantity' => $row->getAggregatedTotalQuantity(),
            ])
            ->all();

        return Inertia::render('reports/Index', [
            'inventory_value' => $currentInventoryValue,
            'monthly' => [
                'incoming' => $monthlyIncomingValue,
                'outgoing' => $monthlyOutgoingValue,
            ],
            'store_consumption' => $storeConsumption,
            'most_moved' => $mostMoved,
            'adjustments' => $adjustments,
            'reasons' => \array_map(
                static fn(AdjustmentReasonEnum $r): string => $r->value,
                AdjustmentReasonEnum::cases(),
            ),
        ]);
    }
}
