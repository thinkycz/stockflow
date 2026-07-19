<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Report;

use App\Enums\StockMovementClassificationEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\InventorySession;
use App\Models\Store;
use App\Models\User;
use App\Services\InventorySessionService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class StatisticsController
{
    /**
     * Lower bound for the configurable period.
     */
    private const int MIN_PERIOD_DAYS = 7;

    /**
     * Upper bound for the configurable period.
     */
    private const int MAX_PERIOD_DAYS = 365;

    /**
     * Default report period.
     */
    private const int DEFAULT_PERIOD_DAYS = 56;

    /**
     * Render inventory statistics for the active store.
     */
    public function __invoke(Request $request, InventorySessionService $inventoryService): Response
    {
        $user = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $user);
        $periodDays = $this->resolvePeriodDays($request);

        if (!$store instanceof Store) {
            return Inertia::render('reports/Statistics', $this->emptyPayload($periodDays));
        }

        $since = Carbon::now()->subDays($periodDays)->startOfDay();
        $storeItems = $store->storeItems()->with('item')->get();
        $inventoryRows = [];
        $inventoryValue = 0.0;
        $positiveSkuCount = 0;
        $risk = ['due_soon' => 0, 'out' => 0, 'no_data' => 0];
        $coverageTotal = 0.0;
        $coveredItems = 0;

        foreach ($storeItems as $storeItem) {
            $item = $storeItem->getItem();
            $quantity = $storeItem->getQuantity();
            $value = $quantity * $item->getPurchasePrice();
            $inventoryValue += $value;
            if ($quantity > 0) {
                ++$positiveSkuCount;
            }

            $periodConsumption = $inventoryService->consumptionLastDays($store, $item, $periodDays, 1000);
            $prediction = $inventoryService->predictedRunOut($store, $item);
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
                'consumed_quantity' => $periodConsumption['quantity'],
                'consumed_value' => \round($periodConsumption['quantity'] * $item->getPurchasePrice(), 2),
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

        $flows = $this->flows($user, $store, $since);
        $classified = $this->classifiedChanges($user, $store, $since);

        return Inertia::render('reports/Statistics', [
            'store' => ['id' => $store->getKey(), 'name' => $store->getName()],
            'period_days' => $periodDays,
            'current_inventory' => [
                'sku_count' => $positiveSkuCount,
                'value' => \round($inventoryValue, 2),
            ],
            'consumption' => [
                'value' => $classified['consumption_value'],
                'affected_skus' => $classified['consumption_skus'],
            ],
            'flows' => $flows,
            'risk' => $risk,
            'data_quality' => [
                'last_inventory_at' => $this->lastInventoryAt($store),
                'average_coverage_days' => $coveredItems > 0 ? \round($coverageTotal / $coveredItems, 1) : 0.0,
                'covered_items' => $coveredItems,
            ],
            'classified_changes' => $classified['reasons'],
            'consumption_series' => $classified['series'],
            'items' => $inventoryRows,
            'filters' => ['store_id' => $store->getKey(), 'period_days' => $periodDays],
        ]);
    }

    /**
     * Parse and clamp the selected period.
     */
    private function resolvePeriodDays(Request $request): int
    {
        $raw = Typer::parseNullableInt($request->query('period_days')) ?? self::DEFAULT_PERIOD_DAYS;

        return \max(self::MIN_PERIOD_DAYS, \min(self::MAX_PERIOD_DAYS, $raw));
    }

    /**
     * Purchase and transfer flows in the selected period.
     *
     * @return array<string, float|int>
     */
    private function flows(User $user, Store $store, Carbon $since): array
    {
        $rows = DB::table('stock_movements')
            ->where('user_id', $user->getKey())
            ->where('occurred_at', '>=', $since->toDateTimeString())
            ->whereIn('type', [StockMovementTypeEnum::INCOMING->value, StockMovementTypeEnum::TRANSFER->value])
            ->get(['type', 'store_id', 'source_store_id', 'total_value']);

        $result = [
            'receipts_value' => 0.0,
            'receipts_count' => 0,
            'transfer_in_value' => 0.0,
            'transfer_in_count' => 0,
            'transfer_out_value' => 0.0,
            'transfer_out_count' => 0,
        ];
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
     * Aggregate consumption, losses, corrections, and weekly value series.
     *
     * @return array{consumption_value: float, consumption_skus: int, reasons: array<int, array<string, mixed>>, series: array<int, array{label: string, value: float}>}
     */
    private function classifiedChanges(User $user, Store $store, Carbon $since): array
    {
        $rows = DB::table('stock_movement_items')
            ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
            ->where('stock_movements.user_id', $user->getKey())
            ->where('stock_movements.store_id', $store->getKey())
            ->where('stock_movements.occurred_at', '>=', $since->toDateTimeString())
            ->whereNotNull('stock_movement_items.classification')
            ->get([
                'stock_movements.occurred_at',
                'stock_movement_items.item_id',
                'stock_movement_items.classification',
                'stock_movement_items.total',
            ]);

        $consumptionValue = 0.0;
        $consumptionItems = [];
        $reasons = [];
        $series = [];
        foreach ($rows as $row) {
            $classification = Typer::assertString($row->classification);
            $value = Typer::parseFloat($row->total);
            if (!isset($reasons[$classification])) {
                $reasons[$classification] = ['classification' => $classification, 'rows_count' => 0, 'value' => 0.0];
            }
            ++$reasons[$classification]['rows_count'];
            $reasons[$classification]['value'] += $value;

            if ($classification !== StockMovementClassificationEnum::CONSUMPTION->value) {
                continue;
            }
            $consumptionValue += $value;
            $consumptionItems[Typer::parseInt($row->item_id)] = true;
            $week = Carbon::parse(Typer::assertString($row->occurred_at))->startOfWeek()->toDateString();
            $series[$week] = ($series[$week] ?? 0.0) + $value;
        }

        return [
            'consumption_value' => \round($consumptionValue, 2),
            'consumption_skus' => \count($consumptionItems),
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
     * Latest physical count for the store.
     */
    private function lastInventoryAt(Store $store): string|null
    {
        $session = InventorySession::query()
            ->where('store_id', $store->getKey())
            ->orderByDesc('counted_at')
            ->first();

        return $session?->getCountedAt()->toJSON();
    }

    /**
     * Empty payload for accounts without a store.
     *
     * @return array<string, mixed>
     */
    private function emptyPayload(int $periodDays): array
    {
        return [
            'store' => null,
            'period_days' => $periodDays,
            'current_inventory' => ['sku_count' => 0, 'value' => 0.0],
            'consumption' => ['value' => 0.0, 'affected_skus' => 0],
            'flows' => ['receipts_value' => 0.0, 'receipts_count' => 0, 'transfer_in_value' => 0.0, 'transfer_in_count' => 0, 'transfer_out_value' => 0.0, 'transfer_out_count' => 0],
            'risk' => ['due_soon' => 0, 'out' => 0, 'no_data' => 0],
            'data_quality' => ['last_inventory_at' => null, 'average_coverage_days' => 0.0, 'covered_items' => 0],
            'classified_changes' => [],
            'consumption_series' => [],
            'items' => [],
            'filters' => ['store_id' => null, 'period_days' => $periodDays],
        ];
    }
}
