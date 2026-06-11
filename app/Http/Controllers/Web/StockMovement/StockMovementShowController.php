<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\StockMovement;

use App\Models\StockMovement;
use App\Models\StockMovementItem;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementShowController
{
    /**
     * Show the movement detail page.
     */
    public function __invoke(StockMovement $stockMovement): Response
    {
        $stockMovement->loadMissing(['store', 'sourceStore', 'creator', 'movementItems.item']);

        $rows = $stockMovement->movementItems->map(static function (StockMovementItem $row): array {
            return [
                'id' => $row->getKey(),
                'item_id' => $row->item_id,
                'item_title' => $row->item?->getTitle(),
                'item_sku' => $row->item?->getSku(),
                'quantity' => $row->getQuantity(),
                'total' => $row->getTotal(),
                'quantity_before' => $row->getQuantityBefore(),
                'quantity_after' => $row->getQuantityAfter(),
                'quantity_difference' => $row->getQuantityDifference(),
                'adjustment_reason' => $row->getAdjustmentReason()?->value,
            ];
        })->all();

        return Inertia::render('stock-movements/Show', [
            'movement' => [
                'id' => $stockMovement->getKey(),
                'number' => $stockMovement->getNumber(),
                'type' => $stockMovement->getType()->value,
                'display_label_key' => $stockMovement->getDisplayLabelKey(),
                'note' => $stockMovement->getNote(),
                'store_id' => $stockMovement->getStoreId(),
                'store_name' => $stockMovement->store?->getName(),
                'source_store_id' => $stockMovement->getSourceStoreId(),
                'source_store_name' => $stockMovement->sourceStore?->getName(),
                'total_quantity' => $stockMovement->getTotalQuantity(),
                'total_value' => $stockMovement->getTotalValue(),
                'created_by' => $stockMovement->creator?->getEmail(),
                'created_at' => $stockMovement->getCreatedAt()->toJSON(),
            ],
            'rows' => $rows,
        ]);
    }
}
