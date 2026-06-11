<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Dashboard;

use App\Enums\ItemStockStatusEnum;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StoreItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /**
     * Render the dashboard.
     */
    public function __invoke(): Response
    {
        $user = User::mustAuth();
        $startOfToday = Carbon::now()->startOfDay();

        $itemsQuery = Item::query();
        Item::scopeForUser($itemsQuery, $user);
        $items = Item::querySelect($itemsQuery)->get();

        $totalInventoryValue = (float) StoreItem::query()
            ->whereHas('item', static function (Builder $query) use ($user): void {
                $query->where('user_id', $user->getKey());
            })
            ->join('items', 'items.id', '=', 'store_items.item_id')
            ->sum(DB::raw('store_items.quantity * items.purchase_price'));

        $totalItems = $items->count();
        $lowStock = $items->filter(
            static fn(Item $item): bool => $item->getStockStatus() === ItemStockStatusEnum::LOW_STOCK ||
                $item->getStockStatus() === ItemStockStatusEnum::OUT_OF_STOCK,
        )->count();

        $todayMovementsQuery = StockMovement::query();
        StockMovement::scopeForUser($todayMovementsQuery, $user);
        $todayMovements = $todayMovementsQuery
            ->where('created_at', '>=', $startOfToday->toDateString())
            ->count();

        $recentMovementsQuery = StockMovement::query();
        StockMovement::scopeForUser($recentMovementsQuery, $user);
        $recentMovements = $recentMovementsQuery
            ->with(['store', 'creator'])
            ->orderByDesc('created_at')
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
                'created_at' => $movement->getCreatedAt()->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('Dashboard', [
            'metrics' => [
                'total_inventory_value' => $totalInventoryValue,
                'total_items' => $totalItems,
                'low_stock_items' => $lowStock,
                'today_movements' => $todayMovements,
            ],
            'recent_movements' => $recentMovements,
        ]);
    }
}
