<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StoreItem;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Inertia\Inertia;
use Inertia\Response;

class ItemShowController
{
    /**
     * Show item details + movement history.
     */
    public function __invoke(Item $item): Response
    {
        $item->loadMissing(['stockMovements' => static function (BelongsToMany $query): void {
            $query->select([
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
        }, 'storeItems.store']);

        $movements = $item->getStockMovements()->map(static function (StockMovement $movement): array {
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
        ]);
    }
}
