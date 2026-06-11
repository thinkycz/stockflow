<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Dashboard;

use App\Enums\ItemStockStatusEnum;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StoreItem;
use App\Models\User;
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
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfToday = $now->copy()->startOfDay();

        $items = Item::querySelect(Item::query()->forUser($user))->get();

        $totalInventoryValue = (float) StoreItem::query()
            ->whereHas('item', static function ($query) use ($user): void {
                $query->forUser($user);
            })
            ->join('items', 'items.id', '=', 'store_items.item_id')
            ->sum(DB::raw('store_items.quantity * items.purchase_price'));

        $totalItems = $items->count();
        $lowStock = $items->filter(
            static fn(Item $item): bool => $item->getStockStatus() === ItemStockStatusEnum::LOW_STOCK ||
                $item->getStockStatus() === ItemStockStatusEnum::OUT_OF_STOCK,
        )->count();

        $todayMovements = StockMovement::query()
            ->forUser($user)
            ->where('created_at', '>=', $startOfToday->toDateString())
            ->count();

        $recentMovements = StockMovement::query()
            ->forUser($user)
            ->with(['store', 'creator'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(static fn(StockMovement $movement): array => [
                'id' => $movement->getKey(),
                'number' => $movement->getNumber(),
                'type' => $movement->getType()->value,
                'store_name' => $movement->store?->getName(),
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
