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

        $rows = $stockMovement->getMovementItems()->map(static function (StockMovementItem $row): array {
            return [
                'id' => $row->getKey(),
                'item_id' => $row->getItemId(),
                'item_title' => $row->getItem()->getTitle(),
                'item_sku' => $row->getItem()->getSku(),
                'quantity' => $row->getQuantity(),
                'total' => $row->getTotal(),
                'quantity_before' => $row->getQuantityBefore(),
                'quantity_after' => $row->getQuantityAfter(),
                'quantity_difference' => $row->getQuantityDifference(),
                'adjustment_reason' => $row->getAdjustmentReason()?->value,
                'classification' => $row->getClassification()?->value,
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
                'store_name' => $stockMovement->getStore()?->getName(),
                'source_store_id' => $stockMovement->getSourceStoreId(),
                'source_store_name' => $stockMovement->getSourceStore()?->getName(),
                'total_quantity' => $stockMovement->getTotalQuantity(),
                'total_value' => $stockMovement->getTotalValue(),
                'reversal_of_id' => $stockMovement->getReversalOfId(),
                'reversal_reason' => $stockMovement->getReversalReason(),
                'reversed_at' => $stockMovement->getReversedAt()?->toJSON(),
                'can_reverse' => $stockMovement->getOrigin()->value === 'manual' &&
                    !\in_array($stockMovement->getType()->value, ['inventory_reconciliation', 'reversal'], true) &&
                    $stockMovement->getReversedAt() === null,
                'created_by' => $stockMovement->getCreator()?->getEmail(),
                'created_at' => $stockMovement->getOccurredAt()->toJSON(),
            ],
            'rows' => $rows,
        ]);
    }
}
