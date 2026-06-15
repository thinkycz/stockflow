<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Enums\StockMovementTypeEnum;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;
use Inertia\Inertia;
use Inertia\Response;

class StoreShowController
{
    /**
     * Show the store detail page with movement history and inventory.
     */
    public function __invoke(Store $store): Response
    {
        $movementsQuery = StockMovement::query();
        StockMovement::scopeForUser($movementsQuery, $store->getUserId());
        $movements = $movementsQuery
            ->where(static function (Builder $query) use ($store): void {
                $query->where('store_id', $store->getKey())
                    ->orWhere('source_store_id', $store->getKey());
            })
            ->with(['creator', 'movementItems.item', 'store', 'sourceStore'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $movementsDto = $movements->map(static function (StockMovement $movement): array {
            $items = $movement->getMovementItems()->map(static fn(StockMovementItem $row): array => [
                'item_id' => $row->getItemId(),
                'item_title' => $row->getItem()->getTitle(),
                'item_sku' => $row->getItem()->getSku(),
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
                'created_by' => $movement->getCreator()?->getEmail(),
                'items' => $items,
            ];
        })->all();

        $inventory = $store->storeItems()
            ->with('item')
            ->where('quantity', '>', 0)
            ->get()
            ->map(static function (StoreItem $row): array {
                $item = $row->getItem();

                return [
                    'item_id' => $row->getItemId(),
                    'item_title' => $item->getTitle(),
                    'item_sku' => $item->getSku(),
                    'quantity' => $row->getQuantity(),
                    'unit' => $item->getUnit(),
                    'purchase_price' => $item->getPurchasePrice(),
                    'total_value' => $row->getQuantity() * $item->getPurchasePrice(),
                ];
            })
            ->all();

        /** @var SupportCollection<array-key, SupportCollection<array-key, StockMovementItem>> $itemRows */
        $itemRows = StockMovementItem::query()
            ->whereHas('stockMovement', static function (Builder $query) use ($store): void {
                $query->where('user_id', $store->getUserId())
                    ->where('store_id', $store->getKey())
                    ->where('type', StockMovementTypeEnum::INCOMING->value);
            })
            ->with('item')
            ->get()
            ->toBase()
            ->groupBy('item_id');

        $itemsReceived = $itemRows->map(static function (SupportCollection $rows, int $itemId): array {
            $first = $rows->first();
            $item = $first instanceof StockMovementItem ? $first->getItem() : null;
            if (!$item instanceof Item) {
                return [];
            }

            $totalQuantity = $rows->sum(static fn(StockMovementItem $row): int => $row->getQuantity() ?? 0);
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

        $outgoingMovements = $movements->filter(
            static fn(StockMovement $movement): bool => $movement->getType() === StockMovementTypeEnum::OUTGOING,
        );
        $totalOutgoingValue = $outgoingMovements->sum(static fn(StockMovement $m): float => $m->getTotalValue());
        $incomingMovements = $movements->filter(
            static fn(StockMovement $movement): bool => $movement->getType() === StockMovementTypeEnum::INCOMING,
        );
        $totalReceivedQuantity = $incomingMovements->sum(static fn(StockMovement $m): int => $m->getTotalQuantity());
        $totalReceivedValue = $incomingMovements->sum(static fn(StockMovement $m): float => $m->getTotalValue());

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
