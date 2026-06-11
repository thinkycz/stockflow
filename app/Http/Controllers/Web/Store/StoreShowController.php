<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use Inertia\Inertia;
use Inertia\Response;

class StoreShowController
{
    /**
     * Show the store detail page with movement history and inventory.
     */
    public function __invoke(Store $store): Response
    {
        $movements = StockMovement::query()
            ->forUser($store->getUserId())
            ->where(static function ($query) use ($store): void {
                $query->where('store_id', $store->getKey())
                    ->orWhere('source_store_id', $store->getKey());
            })
            ->with(['creator', 'movementItems.item', 'store', 'sourceStore'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $movementsDto = $movements->map(static function (StockMovement $movement): array {
            $items = $movement->movementItems->map(static fn(StockMovementItem $row): array => [
                'item_id' => $row->item_id,
                'item_title' => $row->item?->getTitle(),
                'item_sku' => $row->item?->getSku(),
                'quantity' => $row->getQuantity(),
                'total' => $row->getTotal(),
            ])->all();

            return [
                'id' => $movement->getKey(),
                'number' => $movement->getNumber(),
                'type' => $movement->getType()->value,
                'note' => $movement->getNote(),
                'total_quantity' => $movement->getTotalQuantity(),
                'total_value' => $movement->getTotalValue(),
                'created_by' => $movement->creator?->getEmail(),
                'items' => $items,
            ];
        })->all();

        $inventory = $store->storeItems()
            ->with('item')
            ->where('quantity', '>', 0)
            ->get()
            ->map(static fn(StoreItem $row): array => [
                'item_id' => $row->item_id,
                'item_title' => $row->item?->getTitle() ?? '',
                'item_sku' => $row->item?->getSku(),
                'quantity' => $row->getQuantity(),
                'unit' => $row->item?->getUnit(),
                'purchase_price' => $row->item?->getPurchasePrice() ?? 0,
                'total_value' => $row->getQuantity() * ($row->item?->getPurchasePrice() ?? 0),
            ])
            ->all();

        /** @var \Illuminate\Database\Eloquent\Collection<array-key, StockMovementItem> $itemRows */
        $itemRows = StockMovementItem::query()
            ->whereHas('stockMovement', static function ($query) use ($store): void {
                $query->forUser($store->getUserId())
                    ->where('store_id', $store->getKey());
            })
            ->with('item')
            ->get()
            ->groupBy('item_id');

        $itemsReceived = $itemRows->map(static function ($rows, $itemId): array {
            $first = $rows->first();
            $item = $first?->item;
            if (!$item instanceof Item) {
                return [];
            }

            $totalQuantity = $rows->sum(static fn(StockMovementItem $row): float => (float) ($row->getQuantity() ?? 0));
            $totalValue = $rows->sum(static fn(StockMovementItem $row): float => $row->getTotal());

            return [
                'item_id' => $item->getKey(),
                'item_title' => $item->getTitle(),
                'item_sku' => $item->getSku(),
                'movements_count' => $rows->count(),
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue,
            ];
        })->values()->all();

        $outgoingMovements = $movements->where('type', 'outgoing');
        $totalOutgoingValue = $outgoingMovements->sum(static fn(StockMovement $m): float => $m->getTotalValue());
        $totalReceivedQuantity = $movements->where('type', 'incoming')->sum(static fn(StockMovement $m): float => $m->getTotalQuantity());
        $totalReceivedValue = $movements->where('type', 'incoming')->sum(static fn(StockMovement $m): float => $m->getTotalValue());

        return Inertia::render('stores/Show', [
            'store' => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'address' => $store->getAddress(),
                'status' => $store->getStatus()->value,
                'is_warehouse' => $store->isWarehouse(),
                'notes' => $store->getNotes(),
            ],
            'metrics' => [
                'total_outgoing_movements' => $outgoingMovements->count(),
                'total_outgoing_value' => $totalOutgoingValue,
                'total_received_quantity' => $totalReceivedQuantity,
                'total_received_value' => $totalReceivedValue,
            ],
            'inventory' => $inventory,
            'movements' => $movementsDto,
            'items_received' => $itemsReceived,
        ]);
    }
}
