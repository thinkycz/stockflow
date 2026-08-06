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

class ItemShowController
{
    /**
     * Show item details + movement history.
     *
     * Quantities and movement history include every store. The active store is
     * only highlighted in the per-store breakdown.
     */
    public function __invoke(Request $request, Item $item): Response
    {
        $user = User::mustAuth();
        $activeStore = ActiveStoreResolver::resolve($request, $user);

        $item->loadMissing(['storeItems.store']);

        $movementsQuery = $item->stockMovements();

        $movementsQuery->select([
            'stock_movements.id',
            'stock_movements.number',
            'stock_movements.type',
            'stock_movements.store_id',
            'stock_movements.source_store_id',
            'stock_movements.total_quantity',
            'stock_movements.created_at',
        ])->with(['store', 'sourceStore'])
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id')
            ->limit(50);

        $movements = $movementsQuery->get()->map(static function (StockMovement $movement): array {
            return [
                'id' => $movement->getKey(),
                'number' => $movement->getNumber(),
                'type' => $movement->getType()->value,
                'display_label_key' => $movement->getDisplayLabelKey(),
                'store_id' => $movement->getStoreId(),
                'total_quantity' => $movement->getTotalQuantity(),
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

        return Inertia::render('items/Show', [
            'item' => [
                'id' => $item->getKey(),
                'title' => $item->getTitle(),
                'sku' => $item->getSku(),
                'unit' => $item->getUnit(),
                'total_quantity' => $item->getTotalQuantity(),
                'purchase_price' => $item->getPurchasePrice(),
                'total_value' => $item->getTotalValue(),
                'description' => $item->getDescription(),
            ],
            'store_quantities' => $storeQuantities,
            'movements' => $movements,
            'active_store' => $activeStore instanceof Store
                ? [
                    'id' => $activeStore->getKey(),
                    'name' => $activeStore->getName(),
                ]
                : null,
        ]);
    }
}
