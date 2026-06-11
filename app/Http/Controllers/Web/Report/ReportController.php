<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Report;

use App\Enums\AdjustmentReasonEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class ReportController
{
    /**
     * Render the reports page.
     */
    public function __invoke(): Response
    {
        $user = User::mustAuth();
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $userId = $user->getKey();

        $currentInventoryValue = (float) DB::table('store_items')
            ->join('items', 'items.id', '=', 'store_items.item_id')
            ->where('items.user_id', $userId)
            ->sum(DB::raw('store_items.quantity * items.purchase_price'));

        $monthlyIncomingValue = (float) StockMovement::query()
            ->forUser($user)
            ->ofType(StockMovementTypeEnum::INCOMING)
            ->where('created_at', '>=', $startOfMonth->toDateString())
            ->sum('total_value');

        $monthlyOutgoingValue = (float) StockMovement::query()
            ->forUser($user)
            ->ofType(StockMovementTypeEnum::OUTGOING)
            ->where('created_at', '>=', $startOfMonth->toDateString())
            ->sum('total_value');

        $startOfMonthString = $startOfMonth->toDateString();

        $storeConsumption = Store::querySelect(Store::query()->forUser($user)->retail())
            ->get()
            ->map(function (Store $store) use ($startOfMonthString): array {
                $row = DB::table('stock_movements')
                    ->where('store_id', $store->getKey())
                    ->where('type', StockMovementTypeEnum::OUTGOING->value)
                    ->where('created_at', '>=', $startOfMonthString)
                    ->selectRaw('COUNT(*) as movimientos_count, SUM(total_quantity) as total_quantity, SUM(total_value) as total_value')
                    ->first();

                return [
                    'store_id' => $store->getKey(),
                    'store_name' => $store->getName(),
                    'movements_count' => (int) ($row->movements_count ?? 0),
                    'total_quantity' => (float) ($row->total_quantity ?? 0),
                    'total_value' => (float) ($row->total_value ?? 0),
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
            ->map(static fn($row): array => [
                'item_id' => Typer::assertInt($row->id),
                'item_title' => $row->title,
                'item_sku' => $row->sku,
                'total_quantity' => Typer::parseFloat($row->total_quantity),
                'total_value' => Typer::parseFloat($row->total_value),
                'rows_count' => Typer::assertInt($row->rows_count),
            ])
            ->all();

        $adjustments = StockMovementItem::query()
            ->whereHas('stockMovement', static function ($query) use ($user): void {
                // @var \Illuminate\Database\Eloquent\Builder<\App\Models\StockMovement> $query
                $query->forUser($user);
            })
            ->whereNotNull('adjustment_reason')
            ->select('adjustment_reason', DB::raw('COUNT(*) as rows_count'), DB::raw('SUM(ABS(quantity_difference)) as total_quantity'))
            ->groupBy('adjustment_reason')
            ->get()
            ->map(static fn(StockMovementItem $row): array => [
                'reason' => $row->adjustment_reason,
                'rows_count' => (int) $row->rows_count,
                'total_quantity' => (float) $row->total_quantity,
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
