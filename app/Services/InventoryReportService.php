<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementClassificationEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\InventorySession;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryReportService
{
    /**
     * Create the inventory report service.
     */
    public function __construct(
        private readonly InventorySessionService $inventoryService,
    ) {
    }

    /**
     * Build the inventory part of a monthly report.
     *
     * @return array<string, mixed>
     */
    public function build(User $user, Store $store, Carbon $start, Carbon $cutoff): array
    {
        $storeItems = $store->storeItems()->with('item')->get();
        $inventoryRows = [];
        $inventoryValue = 0.0;
        $positiveSkuCount = 0;
        $risk = ['due_soon' => 0, 'out' => 0, 'no_data' => 0];
        $coverageTotal = 0.0;
        $coveredItems = 0;
        $quantities = $this->quantitiesAt($user, $store, $storeItems, $cutoff);
        $predictions = $this->inventoryService->predictionsForQuantities($store, $quantities, $cutoff);
        $classified = $this->classifiedChanges($user, $store, $start, $cutoff);

        foreach ($storeItems as $storeItem) {
            $item = $storeItem->getItem();
            $quantity = $quantities[$item->getKey()];
            $inventoryValue += $quantity * $item->getPurchasePrice();
            if ($quantity > 0) {
                ++$positiveSkuCount;
            }

            $prediction = $predictions[$item->getKey()];
            if ($prediction['status'] === InventorySessionService::STATUS_SOON) {
                ++$risk['due_soon'];
            } elseif ($prediction['status'] === InventorySessionService::STATUS_OUT) {
                ++$risk['out'];
            } elseif ($prediction['status'] === InventorySessionService::STATUS_NO_DATA) {
                ++$risk['no_data'];
            }
            if ($prediction['coverage_days'] >= InventorySessionService::MINIMUM_COVERAGE_DAYS) {
                $coverageTotal += $prediction['coverage_days'];
                ++$coveredItems;
            }

            $inventoryRows[] = [
                'item_id' => $item->getKey(),
                'title' => $item->getTitle(),
                'sku' => $item->getSku(),
                'unit' => $item->getUnit(),
                'current_quantity' => $quantity,
                'consumed_quantity' => $classified['consumption_quantity_by_item'][$item->getKey()] ?? 0,
                'consumed_value' => $classified['consumption_by_item'][$item->getKey()] ?? 0.0,
                'avg_daily_consumption' => $prediction['per_day'],
                'coverage_days' => \round($prediction['coverage_days'], 1),
                'days_until_stockout' => $prediction['days_left'],
                'projected_stockout_at' => $prediction['projected_stockout_at'],
                'status' => $prediction['status'],
            ];
        }

        \usort($inventoryRows, static function (array $left, array $right): int {
            $leftDays = Typer::parseNullableInt($left['days_until_stockout']) ?? \PHP_INT_MAX;
            $rightDays = Typer::parseNullableInt($right['days_until_stockout']) ?? \PHP_INT_MAX;

            $daysComparison = $leftDays <=> $rightDays;

            return $daysComparison !== 0
                ? $daysComparison
                : Typer::assertString($left['title']) <=> Typer::assertString($right['title']);
        });

        return [
            'as_of' => $cutoff->toJSON(),
            'current_inventory' => [
                'sku_count' => $positiveSkuCount,
                'value' => \round($inventoryValue, 2),
                'value_is_estimate' => $cutoff->isPast() && !$cutoff->isToday(),
            ],
            'consumption' => [
                'value' => $classified['consumption_value'],
                'affected_skus' => $classified['consumption_skus'],
            ],
            'flows' => $this->flows($user, $store, $start, $cutoff),
            'risk' => $risk,
            'data_quality' => [
                'last_inventory_at' => $this->lastInventoryAt($store, $cutoff),
                'average_coverage_days' => $coveredItems > 0 ? \round($coverageTotal / $coveredItems, 1) : 0.0,
                'covered_items' => $coveredItems,
            ],
            'classified_changes' => $classified['reasons'],
            'consumption_series' => $classified['series'],
            'items' => $inventoryRows,
        ];
    }

    /**
     * Build an empty inventory payload.
     *
     * @return array<string, mixed>
     */
    public function empty(Carbon $cutoff): array
    {
        return [
            'as_of' => $cutoff->toJSON(),
            'current_inventory' => ['sku_count' => 0, 'value' => 0.0, 'value_is_estimate' => false],
            'consumption' => ['value' => 0.0, 'affected_skus' => 0],
            'flows' => ['receipts_value' => 0.0, 'receipts_count' => 0, 'transfer_in_value' => 0.0, 'transfer_in_count' => 0, 'transfer_out_value' => 0.0, 'transfer_out_count' => 0],
            'risk' => ['due_soon' => 0, 'out' => 0, 'no_data' => 0],
            'data_quality' => ['last_inventory_at' => null, 'average_coverage_days' => 0.0, 'covered_items' => 0],
            'classified_changes' => [],
            'consumption_series' => [],
            'items' => [],
        ];
    }

    /**
     * Reconstruct stock at a past cutoff by reversing every later ledger effect.
     *
     * Transfer rows store the signed source effect; the destination effect has
     * the opposite sign. Reversal rows retain the same store pair and point to
     * the original movement type, so the same perspective rule applies.
     *
     * @param Collection<array-key, StoreItem> $storeItems
     *
     * @return array<int, float|int>
     */
    private function quantitiesAt(User $user, Store $store, Collection $storeItems, Carbon $cutoff): array
    {
        $quantities = [];
        foreach ($storeItems as $storeItem) {
            $quantities[$storeItem->getItemId()] = (float) $storeItem->getQuantity();
        }

        if (!$cutoff->isPast()) {
            return $quantities;
        }

        $rows = DB::table('stock_movement_items')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->leftJoin('stock_movements as original_movements', 'original_movements.id', '=', 'stock_movements.reversal_of_id')
            ->where('stock_movements.user_id', $user->getKey())
            ->where('stock_movements.occurred_at', '>', $cutoff->toDateTimeString())
            ->where(static function (QueryBuilder $query) use ($store): void {
                $query->where('stock_movements.store_id', $store->getKey())
                    ->orWhere('stock_movements.source_store_id', $store->getKey());
            })
            ->get([
                'stock_movement_items.item_id',
                'stock_movement_items.quantity_difference',
                'stock_movements.type',
                'stock_movements.store_id',
                'stock_movements.source_store_id',
                'original_movements.type as original_type',
            ]);

        foreach ($rows as $row) {
            $itemId = Typer::parseInt($row->item_id);
            if (!isset($quantities[$itemId])) {
                continue;
            }
            $difference = Typer::parseFloat($row->quantity_difference);
            $type = Typer::assertString($row->type);
            $effectiveType = $type === StockMovementTypeEnum::REVERSAL->value
                ? Typer::assertString($row->original_type)
                : $type;
            $impact = $effectiveType === StockMovementTypeEnum::TRANSFER->value
                ? (Typer::parseNullableInt($row->source_store_id) === $store->getKey() ? $difference : -$difference)
                : $difference;
            $quantities[$itemId] -= $impact;
        }

        return \array_map(static function (float $quantity): float|int {
            $rounded = \round($quantity, 3);

            return $rounded === \floor($rounded) ? (int) $rounded : $rounded;
        }, $quantities);
    }

    /**
     * Aggregate receipt and transfer flows in the selected month.
     *
     * @return array<string, float|int>
     */
    private function flows(User $user, Store $store, Carbon $start, Carbon $cutoff): array
    {
        $rows = DB::table('stock_movements')
            ->where('user_id', $user->getKey())
            ->whereBetween('occurred_at', [$start->toDateTimeString(), $cutoff->toDateTimeString()])
            ->where(static function (QueryBuilder $query) use ($cutoff): void {
                $query->whereNull('reversed_at')
                    ->orWhere('reversed_at', '>', $cutoff->toDateTimeString());
            })
            ->whereIn('type', [StockMovementTypeEnum::INCOMING->value, StockMovementTypeEnum::TRANSFER->value])
            ->get(['type', 'store_id', 'source_store_id', 'total_value']);
        $result = ['receipts_value' => 0.0, 'receipts_count' => 0, 'transfer_in_value' => 0.0, 'transfer_in_count' => 0, 'transfer_out_value' => 0.0, 'transfer_out_count' => 0];

        foreach ($rows as $row) {
            $type = Typer::assertString($row->type);
            $value = Typer::parseFloat($row->total_value);
            if ($type === StockMovementTypeEnum::INCOMING->value && Typer::parseNullableInt($row->store_id) === $store->getKey()) {
                $result['receipts_value'] += $value;
                ++$result['receipts_count'];
            }
            if ($type === StockMovementTypeEnum::TRANSFER->value && Typer::parseNullableInt($row->store_id) === $store->getKey()) {
                $result['transfer_in_value'] += $value;
                ++$result['transfer_in_count'];
            }
            if ($type === StockMovementTypeEnum::TRANSFER->value && Typer::parseNullableInt($row->source_store_id) === $store->getKey()) {
                $result['transfer_out_value'] += $value;
                ++$result['transfer_out_count'];
            }
        }

        return $result;
    }

    /**
     * Aggregate classified stock changes in the selected month.
     *
     * @return array{consumption_value: float, consumption_skus: int, consumption_by_item: array<int, float>, consumption_quantity_by_item: array<int, float|int>, reasons: array<int, array<string, mixed>>, series: array<int, array{label: string, value: float}>}
     */
    private function classifiedChanges(User $user, Store $store, Carbon $start, Carbon $cutoff): array
    {
        $rows = DB::table('stock_movement_items')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->where('stock_movements.user_id', $user->getKey())
            ->where('stock_movements.store_id', $store->getKey())
            ->whereBetween('stock_movements.occurred_at', [$start->toDateTimeString(), $cutoff->toDateTimeString()])
            ->where(static function (QueryBuilder $query) use ($cutoff): void {
                $query->whereNull('stock_movements.reversed_at')
                    ->orWhere('stock_movements.reversed_at', '>', $cutoff->toDateTimeString());
            })
            ->where('stock_movements.type', '!=', StockMovementTypeEnum::REVERSAL->value)
            ->whereNotNull('stock_movement_items.classification')
            ->get(['stock_movements.occurred_at', 'stock_movement_items.item_id', 'stock_movement_items.classification', 'stock_movement_items.quantity_difference', 'stock_movement_items.total']);
        $consumptionValue = 0.0;
        $consumptionItems = [];
        $consumptionByItem = [];
        $consumptionQuantityByItem = [];
        $reasons = [];
        $series = [];

        foreach ($rows as $row) {
            $classification = Typer::assertString($row->classification);
            $value = Typer::parseFloat($row->total);
            $reasons[$classification] ??= ['classification' => $classification, 'rows_count' => 0, 'value' => 0.0];
            ++$reasons[$classification]['rows_count'];
            $reasons[$classification]['value'] += $value;

            if ($classification !== StockMovementClassificationEnum::CONSUMPTION->value) {
                continue;
            }
            $consumptionValue += $value;
            $itemId = Typer::parseInt($row->item_id);
            $consumptionItems[$itemId] = true;
            $consumptionByItem[$itemId] = ($consumptionByItem[$itemId] ?? 0.0) + $value;
            $consumptionQuantityByItem[$itemId] = ($consumptionQuantityByItem[$itemId] ?? 0.0) - Typer::parseFloat($row->quantity_difference);
            $week = Carbon::parse(Typer::assertString($row->occurred_at))->startOfWeek()->toDateString();
            $series[$week] = ($series[$week] ?? 0.0) + $value;
        }

        return [
            'consumption_value' => \round($consumptionValue, 2),
            'consumption_skus' => \count($consumptionItems),
            'consumption_by_item' => \array_map(static fn(float $value): float => \round($value, 2), $consumptionByItem),
            'consumption_quantity_by_item' => \array_map(static function (float $quantity): float|int {
                $rounded = \round($quantity, 3);

                return $rounded === \floor($rounded) ? (int) $rounded : $rounded;
            }, $consumptionQuantityByItem),
            'reasons' => \array_values(\array_map(static function (array $row): array {
                $row['value'] = \round(Typer::parseFloat($row['value']), 2);

                return $row;
            }, $reasons)),
            'series' => \array_map(
                static fn(string $label, float $value): array => ['label' => $label, 'value' => \round($value, 2)],
                \array_keys($series),
                \array_values($series),
            ),
        ];
    }

    /**
     * Latest closed physical count available at the reporting cutoff.
     */
    private function lastInventoryAt(Store $store, Carbon $cutoff): string|null
    {
        $session = InventorySession::query()
            ->where('store_id', $store->getKey())
            ->where('status', 'closed')
            ->where('counted_at', '<=', $cutoff->toDateTimeString())
            ->orderByDesc('counted_at')
            ->first();

        return $session?->getCountedAt()->toJSON();
    }
}
