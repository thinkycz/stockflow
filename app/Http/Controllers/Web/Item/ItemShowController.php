<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class ItemShowController
{
    /**
     * Show item details + movement history.
     *
     * Movements are filtered to the active store when one is resolved,
     * so the user sees only the movements that affected the currently
     * selected store. The active store's quantity is highlighted in the
     * store quantities table.
     */
    public function __invoke(Request $request, Item $item): Response
    {
        $user = User::mustAuth();
        $activeStore = ActiveStoreResolver::resolve($request, $user);

        $item->loadMissing(['storeItems.store']);

        $movementsQuery = $item->stockMovements();

        if ($activeStore instanceof Store) {
            $storeId = $activeStore->getKey();
            $movementsQuery->where(static function ($query) use ($storeId): void {
                $query->where('store_id', $storeId)
                    ->orWhere('source_store_id', $storeId);
            });
        }

        $movementsQuery->select([
            'stock_movements.id',
            'stock_movements.number',
            'stock_movements.type',
            'stock_movements.store_id',
            'stock_movements.total_quantity',
            'stock_movements.total_value',
            'stock_movements.created_at',
        ])
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id')
            ->limit(50);

        $movements = $movementsQuery->get()->map(static function (StockMovement $movement): array {
            return [
                'id' => $movement->getKey(),
                'number' => $movement->getNumber(),
                'type' => $movement->getType()->value,
                'store_id' => $movement->getStoreId(),
                'total_quantity' => $movement->getTotalQuantity(),
                'total_value' => $movement->getTotalValue(),
                'quantity' => $movement->getPivotQuantity(),
                'quantity_before' => $movement->getPivotQuantityBefore(),
                'quantity_after' => $movement->getPivotQuantityAfter(),
                'quantity_difference' => $movement->getPivotQuantityDifference(),
                'adjustment_reason' => $movement->getPivotAdjustmentReason(),
                'created_at' => $movement->getCreatedAt()->toJSON(),
            ];
        })->all();

        $storeQuantities = $item->getStoreItems()->map(static function (StoreItem $row): array {
            $store = $row->getStore();

            return [
                'store_id' => $row->getStoreId(),
                'store_name' => $store->getName(),
                'is_warehouse' => $store->isWarehouse(),
                'quantity' => $row->getQuantity(),
            ];
        })->all();

        $activeStoreQuantity = null;
        if ($activeStore instanceof Store) {
            $raw = StoreItem::query()
                ->where('item_id', $item->getKey())
                ->where('store_id', $activeStore->getKey())
                ->value('quantity');
            $activeStoreQuantity = $raw !== null ? Typer::parseInt($raw) : 0;
        }

        return Inertia::render('items/Show', [
            'item' => [
                'id' => $item->getKey(),
                'title' => $item->getTitle(),
                'sku' => $item->getSku(),
                'unit' => $item->getUnit(),
                'warehouse_quantity' => $item->getWarehouseQuantity(),
                'total_quantity' => $item->getTotalQuantity(),
                'purchase_price' => $item->getPurchasePrice(),
                'total_value' => $item->getTotalValue(),
                'description' => $item->getDescription(),
                'status' => $item->getStockStatus()->value,
                'created_at' => $item->getCreatedAt()->toJSON(),
            ],
            'store_quantities' => $storeQuantities,
            'movements' => $movements,
            'active_store' => $activeStore instanceof Store
                ? [
                    'id' => $activeStore->getKey(),
                    'name' => $activeStore->getName(),
                    'quantity' => $activeStoreQuantity,
                ]
                : null,
        ]);
    }
}
