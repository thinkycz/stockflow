<?php

declare(strict_types=1);

namespace App\Domain\Stores;

use App\Domain\Inventory\InventoryReadService;
use App\Enums\StockMovementTypeEnum;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

class StoreDetailService
{
    /**
     * Build bounded store history with independent all-time summaries.
     *
     * @return array<string, mixed>
     */
    public function build(Store $store, InventoryReadService $counts): array
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
            ->paginate(50);

        $movementsDto = $movements->getCollection()->map(static function (StockMovement $movement): array {
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
                'display_label_key' => $movement->getDisplayLabelKey(),
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

        $itemsReceived = DB::table('stock_movement_items')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->join('items', 'items.id', '=', 'stock_movement_items.item_id')
            ->where('stock_movements.user_id', $store->getUserId())
            ->where('stock_movements.store_id', $store->getKey())
            ->where('stock_movements.type', StockMovementTypeEnum::INCOMING->value)
            ->selectRaw('items.id AS item_id, items.title AS item_title, items.sku AS item_sku, COUNT(*) AS movements_count, SUM(stock_movement_items.quantity) AS total_quantity, SUM(stock_movement_items.total) AS total_value')
            ->groupBy('items.id', 'items.title', 'items.sku')
            ->orderBy('items.id')
            ->get()->map(static fn(object $row): array => [
                'item_id' => Typer::parseInt($row->item_id),
                'item_title' => Typer::assertString($row->item_title),
                'item_sku' => Typer::assertNullableString($row->item_sku),
                'movements_count' => Typer::parseInt($row->movements_count),
                'total_quantity' => Typer::parseFloat($row->total_quantity ?? 0),
                'total_value' => Typer::parseFloat($row->total_value ?? 0),
            ])->all();
        $outgoing = StockMovement::query()->where('user_id', $store->getUserId())
            ->where('source_store_id', $store->getKey())->where('type', StockMovementTypeEnum::TRANSFER->value);
        $incoming = StockMovement::query()->where('user_id', $store->getUserId())
            ->where('store_id', $store->getKey())->where('type', StockMovementTypeEnum::INCOMING->value);

        return [
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
                'total_transfer_out_movements' => (clone $outgoing)->count(),
                'total_transfer_out_value' => Typer::parseFloat((clone $outgoing)->sum('total_value')),
                'total_received_quantity' => Typer::parseInt((clone $incoming)->sum('total_quantity')),
                'total_received_value' => Typer::parseFloat((clone $incoming)->sum('total_value')),
            ],
            'inventory' => $inventory,
            'movements' => $movementsDto,
            'movements_pagination' => ['current_page' => $movements->currentPage(), 'last_page' => $movements->lastPage(), 'per_page' => $movements->perPage(), 'total' => $movements->total()],
            'items_received' => $itemsReceived,
            'now' => Carbon::now()->toJSON(),
        ];
    }
}
