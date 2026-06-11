<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Item;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StoreItem;
use Inertia\Inertia;
use Inertia\Response;

class ItemShowController
{
    /**
     * Show item details + movement history.
     */
    public function __invoke(Item $item): Response
    {
        $item->loadMissing(['stockMovements' => static function ($query): void {
            // @var \Illuminate\Database\Eloquent\Builder<StockMovement> $query
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

        $movements = $item->stockMovements->map(static function (StockMovement $movement): array {
            $pivot = $movement->pivot;

            return [
                'id' => $movement->getKey(),
                'number' => $movement->getNumber(),
                'type' => $movement->getType()->value,
                'store_id' => $movement->getStoreId(),
                'total_quantity' => $movement->getTotalQuantity(),
                'total_value' => $movement->getTotalValue(),
                'quantity' => $pivot ? (float) $pivot->quantity : null,
                'quantity_before' => $pivot && $pivot->quantity_before !== null ? (float) $pivot->quantity_before : null,
                'quantity_after' => $pivot && $pivot->quantity_after !== null ? (float) $pivot->quantity_after : null,
                'quantity_difference' => $pivot && $pivot->quantity_difference !== null ? (float) $pivot->quantity_difference : null,
                'adjustment_reason' => $pivot?->adjustment_reason,
            ];
        })->all();

        $storeQuantities = $item->storeItems->map(static fn(StoreItem $row): array => [
            'store_id' => $row->store_id,
            'store_name' => $row->store?->getName() ?? '',
            'is_warehouse' => $row->store?->isWarehouse() ?? false,
            'quantity' => $row->getQuantity(),
        ])->all();

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
