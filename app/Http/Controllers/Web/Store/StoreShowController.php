<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Enums\StockMovementTypeEnum;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class StoreShowController
{
    /**
     * Show the store detail page with movement history and inventory.
     */
    public function __invoke(Store $store, InventorySessionService $counts): Response
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
                'created_at' => $movement->getCreatedAt()->toJSON(),
                'items' => $items,
            ];
        })->all();

        $owner = User::query()->whereKey($store->getUserId())->first() ?? User::mustAuth();
        $storeItems = $store->storeItems()->with('item')->get();
        $itemIds = \array_values($storeItems->map(static fn(StoreItem $row): int => $row->getItemId())->all());
        $predictions = $counts->predictionsForStore($store, $storeItems);
        $sparklines = $counts->sparklinesForItems($owner, $store, $itemIds, 30);
        $lastCounts = DB::table('inventory_session_items')
            ->join('inventory_sessions', 'inventory_sessions.id', '=', 'inventory_session_items.session_id')
            ->where('inventory_sessions.store_id', $store->getKey())
            ->where('inventory_sessions.status', 'closed')
            ->whereIn('inventory_session_items.item_id', $itemIds)
            ->selectRaw(
                'inventory_session_items.item_id, MAX(inventory_sessions.counted_at) AS last_counted_at',
            )
            ->groupBy('inventory_session_items.item_id')
            ->pluck('last_counted_at', 'inventory_session_items.item_id');

        $inventory = $storeItems
            ->map(static function (StoreItem $row) use ($lastCounts, $predictions, $sparklines): array {
                $item = $row->getItem();
                $quantity = $row->getQuantity();
                $prediction = $predictions[$item->getKey()];
                $lastCount = $lastCounts->get($item->getKey());

                return [
                    'item_id' => $row->getItemId(),
                    'item_title' => $item->getTitle(),
                    'item_sku' => $item->getSku(),
                    'quantity' => $quantity,
                    'unit' => $item->getUnit(),
                    'purchase_price' => $item->getPurchasePrice(),
                    'total_value' => $quantity * $item->getPurchasePrice(),
                    'status' => $prediction['status'],
                    'sparkline' => $sparklines[$item->getKey()],
                    'last_count_at' => $lastCount === null ? null : Carbon::parse(Typer::assertString($lastCount))->toJSON(),
                    'avg_daily_consumption' => $prediction['per_day'],
                    'coverage_days' => $prediction['coverage_days'],
                    'days_until_stockout' => $prediction['days_left'],
                    'projected_stockout_at' => $prediction['projected_stockout_at'],
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

            $totalQuantity = $rows->sum(static fn(StockMovementItem $row): float|int => $row->getQuantity() ?? 0);
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
            static fn(StockMovement $movement): bool => $movement->getType() === StockMovementTypeEnum::TRANSFER,
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
                'slack_channel' => $store->getSlackChannel(),
            ],
            'metrics' => [
                'total_transfer_out_movements' => $outgoingMovements->count(),
                'total_transfer_out_value' => $totalOutgoingValue,
                'total_received_quantity' => $totalReceivedQuantity,
                'total_received_value' => $totalReceivedValue,
            ],
            'inventory' => $inventory,
            'movements' => $movementsDto,
            'items_received' => $itemsReceived,
            'now' => Carbon::now()->toJSON(),
        ]);
    }
}
