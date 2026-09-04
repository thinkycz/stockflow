<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Enums\StockMovementClassificationEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryReadService
{
    /**
     * Window (in days) used when computing average daily consumption.
     */
    public const int CONSUMPTION_WINDOW_DAYS = 56;

    /**
     * Minimum closed observation coverage required for a forecast.
     */
    public const int MINIMUM_COVERAGE_DAYS = 7;

    /**
     * Maximum number of closed inventory intervals used by a forecast.
     */
    public const int MAXIMUM_INTERVALS = 8;

    /**
     * Days of stock threshold below which a row is flagged as "soon".
     */
    public const int SOON_THRESHOLD_DAYS = 7;

    /**
     * Possible status values for predictedRunOut.
     */
    public const string STATUS_OK = 'ok';

    public const string STATUS_SOON = 'due_soon';

    public const string STATUS_OUT = 'out';

    public const string STATUS_NO_DATA = 'no_data';

    /**
     * Current quantity on hand for the given store and item.
     */
    public function currentQuantity(Store $store, Item $item): float|int
    {
        $row = StoreItem::query()
            ->where('store_id', $store->getKey())
            ->where('item_id', $item->getKey())
            ->first();

        if ($row === null) {
            return 0;
        }

        return $row->getQuantity();
    }

    /**
     * Quantity from the most recent prior inventory session for the
     * given store and item. Returns null when no prior session exists.
     */
    public function previousQuantity(Store $store, Item $item, Carbon|null $before = null): float|int|null
    {
        $query = DB::table('inventory_session_items')
            ->join('inventory_sessions', 'inventory_sessions.id', '=', 'inventory_session_items.session_id')
            ->where('inventory_sessions.store_id', $store->getKey())
            ->where('inventory_session_items.item_id', $item->getKey())
            ->orderByDesc('inventory_sessions.counted_at')
            ->orderByDesc('inventory_session_items.id')
            ->select('inventory_session_items.quantity');

        if ($before instanceof Carbon) {
            $query->where('inventory_sessions.counted_at', '<', $before->toDateTimeString());
        }

        $value = $query->value('inventory_session_items.quantity');

        return $value === null ? null : $this->number($value);
    }

    /**
     * Timestamp of the most recent prior physical count.
     */
    public function previousCountedAt(Store $store, Item $item, Carbon|null $before = null): Carbon|null
    {
        $query = DB::table('inventory_session_items')
            ->join('inventory_sessions', 'inventory_sessions.id', '=', 'inventory_session_items.session_id')
            ->where('inventory_sessions.store_id', $store->getKey())
            ->where('inventory_session_items.item_id', $item->getKey())
            ->orderByDesc('inventory_sessions.counted_at')
            ->orderByDesc('inventory_session_items.id');

        if ($before instanceof Carbon) {
            $query->where('inventory_sessions.counted_at', '<', $before->toDateTimeString());
        }

        $value = $query->value('inventory_sessions.counted_at');

        return $value === null ? null : Carbon::parse(Typer::assertString($value));
    }

    /**
     * Calculate consumption across closed physical-count intervals.
     *
     * @return array{quantity: float|int, per_day: float, coverage_days: float}
     */
    public function consumptionLastDays(
        Store $store,
        Item $item,
        int $days = self::CONSUMPTION_WINDOW_DAYS,
        int $maximumIntervals = self::MAXIMUM_INTERVALS,
    ): array
    {
        return $this->consumptionForItems($store, [$item->getKey()], $days, $maximumIntervals)[$item->getKey()];
    }

    /**
     * Load all closed intervals and manual consumption for a store in two queries.
     *
     * @param list<int> $itemIds
     *
     * @return array<int, array{quantity: float|int, per_day: float, coverage_days: float}>
     */
    public function consumptionForItems(
        Store $store,
        array $itemIds,
        int $days = self::CONSUMPTION_WINDOW_DAYS,
        int $maximumIntervals = self::MAXIMUM_INTERVALS,
        Carbon|null $asOf = null,
    ): array
    {
        $asOf ??= Carbon::now();
        $since = $asOf->copy()->subDays($days)->startOfDay();
        $intervals = DB::table('inventory_session_items')
            ->join('inventory_sessions', 'inventory_sessions.id', '=', 'inventory_session_items.session_id')
            ->where('inventory_sessions.user_id', $store->getUserId())
            ->where('inventory_sessions.store_id', $store->getKey())
            ->where('inventory_sessions.status', 'closed')
            ->whereIn('inventory_session_items.item_id', $itemIds)
            ->whereNotNull('inventory_session_items.observation_started_at')
            ->where('inventory_sessions.counted_at', '>=', $since->toDateTimeString())
            ->where('inventory_sessions.counted_at', '<=', $asOf->toDateTimeString())
            ->orderByDesc('inventory_sessions.counted_at')
            ->get([
                'inventory_session_items.item_id',
                DB::raw('COALESCE(inventory_session_items.counted_at, inventory_sessions.counted_at) as interval_ended_at'),
                'inventory_session_items.observation_started_at',
                'inventory_session_items.quantity_difference',
                'inventory_session_items.classification',
            ]);

        /** @var array<int, array{consumed: float, coverage_seconds: int, ranges: list<array{Carbon, Carbon}>, intervals: int}> $states */
        $states = [];
        foreach ($itemIds as $itemId) {
            $states[$itemId] = ['consumed' => 0.0, 'coverage_seconds' => 0, 'ranges' => [], 'intervals' => 0];
        }

        foreach ($intervals as $interval) {
            $itemId = Typer::parseInt($interval->item_id);
            if (!isset($states[$itemId]) || $states[$itemId]['intervals'] >= $maximumIntervals) {
                continue;
            }
            ++$states[$itemId]['intervals'];
            $start = Carbon::parse(Typer::assertString($interval->observation_started_at));
            $end = Carbon::parse(Typer::assertString($interval->interval_ended_at));
            $effectiveStart = $start->lessThan($since) ? $since->copy() : $start;

            if ($effectiveStart->greaterThanOrEqualTo($end)) {
                continue;
            }

            $effectiveSeconds = (int) $effectiveStart->diffInSeconds($end);
            $fullSeconds = (int) $start->diffInSeconds($end);
            $states[$itemId]['coverage_seconds'] += $effectiveSeconds;
            $states[$itemId]['ranges'][] = [$effectiveStart, $end];

            if (
                StockMovementClassificationEnum::CONSUMPTION->value === Typer::parseNullableString($interval->classification) &&
                Typer::parseFloat($interval->quantity_difference) < 0.0
            ) {
                $states[$itemId]['consumed'] += \abs(Typer::parseFloat($interval->quantity_difference)) * ($effectiveSeconds / $fullSeconds);
            }
        }

        if ($itemIds !== []) {
            $manualRows = DB::table('stock_movement_items')
                ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
                ->where('stock_movements.user_id', $store->getUserId())
                ->where('stock_movements.store_id', $store->getKey())
                ->where('stock_movements.type', StockMovementTypeEnum::CONSUMPTION->value)
                ->whereNull('stock_movements.reversed_at')
                ->whereIn('stock_movement_items.item_id', $itemIds)
                ->where('stock_movements.occurred_at', '>=', $since->toDateTimeString())
                ->where('stock_movements.occurred_at', '<=', $asOf->toDateTimeString())
                ->get(['stock_movement_items.item_id', 'stock_movements.occurred_at', 'stock_movement_items.quantity']);
            foreach ($manualRows as $manualRow) {
                $itemId = Typer::parseInt($manualRow->item_id);
                if (!isset($states[$itemId])) {
                    continue;
                }
                $occurredAt = Carbon::parse(Typer::assertString($manualRow->occurred_at));
                foreach ($states[$itemId]['ranges'] as [$rangeStart, $rangeEnd]) {
                    if ($occurredAt->betweenIncluded($rangeStart, $rangeEnd)) {
                        $states[$itemId]['consumed'] += Typer::parseFloat($manualRow->quantity);
                        break;
                    }
                }
            }
        }

        $result = [];
        foreach ($states as $itemId => $state) {
            $coverageDays = $state['coverage_seconds'] / 86400;
            $roundedConsumed = \round($state['consumed'], 3);
            $result[$itemId] = [
                'quantity' => $roundedConsumed === \floor($roundedConsumed) ? (int) $roundedConsumed : $roundedConsumed,
                'per_day' => $coverageDays >= self::MINIMUM_COVERAGE_DAYS ? $state['consumed'] / $coverageDays : 0.0,
                'coverage_days' => $coverageDays,
            ];
        }

        return $result;
    }

    /**
     * Forecast when the store will run out of an item based on the
     * configured consumption window.
     *
     * @return array{current: float|int, per_day: float, coverage_days: float, days_left: int|null, projected_stockout_at: string|null, status: string}
     */
    public function predictedRunOut(Store $store, Item $item, int $days = self::CONSUMPTION_WINDOW_DAYS): array
    {
        $current = $this->currentQuantity($store, $item);
        $consumption = $this->consumptionLastDays($store, $item, $days);

        return $this->predictionFromConsumption($current, $consumption);
    }

    /**
     * @param Collection<array-key, StoreItem> $storeItems
     *
     * @return array<int, array{current: float|int, per_day: float, coverage_days: float, days_left: int|null, projected_stockout_at: string|null, status: string}>
     */
    public function predictionsForStore(Store $store, Collection $storeItems): array
    {
        $consumption = $this->consumptionForItems(
            $store,
            \array_values($storeItems->map(static fn(StoreItem $row): int => $row->getItemId())->all()),
        );
        $result = [];
        foreach ($storeItems as $storeItem) {
            $result[$storeItem->getItemId()] = $this->predictionFromConsumption(
                $storeItem->getQuantity(),
                $consumption[$storeItem->getItemId()],
            );
        }

        return $result;
    }

    /**
     * Forecast supplied item quantities using only data available at a cutoff.
     *
     * @param array<int, float|int> $quantities
     *
     * @return array<int, array{current: float|int, per_day: float, coverage_days: float, days_left: int|null, projected_stockout_at: string|null, status: string}>
     */
    public function predictionsForQuantities(Store $store, array $quantities, Carbon $asOf): array
    {
        $consumption = $this->consumptionForItems(
            $store,
            \array_keys($quantities),
            self::CONSUMPTION_WINDOW_DAYS,
            self::MAXIMUM_INTERVALS,
            $asOf,
        );
        $result = [];
        foreach ($quantities as $itemId => $quantity) {
            $result[$itemId] = $this->predictionFromConsumption($quantity, $consumption[$itemId], $asOf);
        }

        return $result;
    }

    /**
     * Build a per-item view of the selected store's inventory for the
     * inventory editor. Rows are sorted alphabetically by item title.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildStoreView(User $user, Store $store): array
    {
        $itemsQuery = Item::query();
        Item::scopeForUser($itemsQuery, $user);
        $items = Item::querySelect($itemsQuery)
            ->orderBy('title')
            ->get();

        $currentByItem = StoreItem::query()
            ->where('store_id', $store->getKey())
            ->get()
            ->keyBy(static fn(StoreItem $row): int => $row->getItemId());
        $rows = [];

        foreach ($items as $item) {
            $itemId = $item->getKey();
            $storeItem = $currentByItem->get($itemId);
            $current = $storeItem instanceof StoreItem ? $storeItem->getQuantity() : 0;

            $rows[] = [
                'item_id' => $itemId,
                'title' => $item->getTitle(),
                'sku' => $item->getSku(),
                'unit' => $item->getUnit(),
                'current' => $current,
            ];
        }

        return $rows;
    }

    /**
     * Build a chronological list of inventory sessions for the given
     * store in the given date range. When an item is provided, only
     * sessions that contain a row for that item are returned.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historyForUser(User $user, Store $store, Item|null $item, Carbon $from, Carbon $to, int $limit): array
    {
        $query = InventorySession::query();
        InventorySession::scopeForUser($query, $user);
        InventorySession::scopeForStore($query, $store->getKey());
        InventorySession::scopeBetween($query, $from, $to);
        $query->where('status', 'closed');

        if ($item instanceof Item) {
            $query->whereHas('items', static function ($q) use ($item): void {
                $q->where('item_id', $item->getKey());
            });
        }

        $sessions = $query
            ->withCount('items')
            ->orderByDesc('counted_at')
            ->orderByDesc('id')
            ->take($limit)
            ->get();

        $creatorIds = $sessions->pluck('created_by')->filter()->unique()->values()->all();

        $creators = User::query()
            ->whereIn('id', $creatorIds)
            ->get()
            ->keyBy(static fn(User $u): int => $u->getKey());

        return $sessions->map(static function (InventorySession $session) use ($creators): array {
            $createdBy = $session->getCreatedBy();
            $creator = $createdBy !== null ? $creators->get($createdBy) : null;

            $itemsCount = $session->getAttribute('items_count');
            $itemCount = $itemsCount === null ? 0 : Typer::parseInt($itemsCount);

            return [
                'id' => $session->getKey(),
                'counted_at' => $session->getCountedAt()->toJSON(),
                'note' => $session->getNote(),
                'created_by' => $createdBy,
                'created_by_email' => $creator instanceof User ? $creator->getEmail() : null,
                'item_count' => $itemCount,
            ];
        })->all();
    }

    /**
     * Build the read-only item list for a single inventory session.
     * Items appear in alphabetical order. Each row exposes the new
     * quantity recorded in the session and the expected stock quantity
     * immediately before the item was counted (null for legacy rows).
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildSessionView(User $user, InventorySession $session): array
    {
        $itemsQuery = Item::withTrashed();
        Item::scopeForUser($itemsQuery, $user->resolveScopeUser());
        $items = $itemsQuery
            ->orderBy('title')
            ->get()
            ->keyBy(static fn(Item $item): int => $item->getKey());

        $sessionItems = $session->items()->get()->keyBy(
            static fn(InventorySessionItem $row): int => $row->getItemId(),
        );

        $rows = [];

        foreach ($items as $item) {
            $itemId = $item->getKey();
            $sessionItem = $sessionItems->get($itemId);

            if (!$sessionItem instanceof InventorySessionItem) {
                continue;
            }

            $rows[] = [
                'item_id' => $itemId,
                'title' => $item->getTitle(),
                'sku' => $item->getSku(),
                'unit' => $item->getUnit(),
                'current' => $sessionItem->getQuantity(),
                'revision' => $sessionItem->getRevision(),
                'expected' => $sessionItem->getExpectedQuantity(),
                'difference' => $sessionItem->getQuantityDifference(),
                'classification' => $sessionItem->getClassification()?->value,
                'note' => $sessionItem->getNote(),
            ];
        }

        return $rows;
    }

    /**
     * Build a dense day-by-day sparkline of session quantities for the
     * given store/item pair over the last `$days` days.
     *
     * Days without a recorded session are returned as `null` so the UI
     * can render a gap (the count is unknown for that day).
     *
     * @return array<int, array{label: string, value: float|int|null}>
     */
    public function sparklineForItem(User $user, Store $store, Item $item, int $days = 30): array
    {
        return $this->sparklinesForItems($user, $store, [$item->getKey()], $days)[$item->getKey()];
    }

    /**
     * @param list<int> $itemIds
     *
     * @return array<int, array<int, array{label: string, value: float|int|null}>>
     */
    public function sparklinesForItems(User $user, Store $store, array $itemIds, int $days = 30): array
    {
        $today = Carbon::now()->endOfDay();
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        $records = DB::table('inventory_session_items')
            ->join('inventory_sessions', 'inventory_sessions.id', '=', 'inventory_session_items.session_id')
            ->where('inventory_sessions.user_id', $user->getKey())
            ->where('inventory_sessions.store_id', $store->getKey())
            ->whereIn('inventory_session_items.item_id', $itemIds)
            ->where('inventory_sessions.status', 'closed')
            ->where('inventory_sessions.counted_at', '>=', $from->toDateTimeString())
            ->orderBy('inventory_sessions.counted_at')
            ->get(['inventory_session_items.item_id', 'inventory_sessions.counted_at', 'inventory_session_items.quantity']);

        $byDay = [];

        foreach ($records as $record) {
            $countedAt = Carbon::parse(Typer::assertString($record->counted_at));
            $byDay[Typer::parseInt($record->item_id)][$countedAt->toDateString()] = $this->number($record->quantity);
        }

        $result = [];
        foreach ($itemIds as $itemId) {
            $sparkline = [];
            $cursor = $from->copy();
            while ($cursor->lessThanOrEqualTo($today)) {
                $key = $cursor->toDateString();
                $sparkline[] = ['label' => $key, 'value' => $byDay[$itemId][$key] ?? null];
                $cursor->addDay();
            }
            $result[$itemId] = $sparkline;
        }

        return $result;
    }

    /**
     * @param array{quantity: float|int, per_day: float, coverage_days: float} $consumption
     *
     * @return array{current: float|int, per_day: float, coverage_days: float, days_left: int|null, projected_stockout_at: string|null, status: string}
     */
    private function predictionFromConsumption(float|int $current, array $consumption, Carbon|null $asOf = null): array
    {
        $asOf ??= Carbon::now();
        $perDay = $consumption['per_day'];

        if ($current <= 0) {
            return [
                'current' => $current,
                'per_day' => $perDay,
                'coverage_days' => $consumption['coverage_days'],
                'days_left' => 0,
                'projected_stockout_at' => $asOf->toDateString(),
                'status' => self::STATUS_OUT,
            ];
        }

        if ($perDay <= 0.0 || $consumption['coverage_days'] < self::MINIMUM_COVERAGE_DAYS) {
            return [
                'current' => $current,
                'per_day' => $perDay,
                'coverage_days' => $consumption['coverage_days'],
                'days_left' => null,
                'projected_stockout_at' => null,
                'status' => self::STATUS_NO_DATA,
            ];
        }

        $daysLeft = (int) \floor($current / $perDay);
        $status = $daysLeft <= self::SOON_THRESHOLD_DAYS ? self::STATUS_SOON : self::STATUS_OK;

        return [
            'current' => $current,
            'per_day' => $perDay,
            'coverage_days' => $consumption['coverage_days'],
            'days_left' => $daysLeft,
            'projected_stockout_at' => $asOf->copy()->addDays($daysLeft)->toDateString(),
            'status' => $status,
        ];
    }

    /**
     * Convert a database decimal to a presentation number.
     */
    private function number(mixed $value): float|int
    {
        $number = (float) Typer::assertScalar($value);

        return $number === \floor($number) ? (int) $number : $number;
    }
}
