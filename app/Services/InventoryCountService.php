<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementTypeEnum;
use App\Models\InventoryCount;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockMovementItem;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryCountService
{
    /**
     * Window (in days) used when computing average daily consumption.
     */
    public const int CONSUMPTION_WINDOW_DAYS = 30;

    /**
     * Days of stock threshold below which a row is flagged as "soon".
     */
    public const int SOON_THRESHOLD_DAYS = 7;

    /**
     * Possible status values for predictedRunOut and the per-row view.
     */
    public const string STATUS_OK = 'ok';

    public const string STATUS_SOON = 'soon';

    public const string STATUS_OUT = 'out';

    public const string STATUS_NO_DATA = 'no_data';

    /**
     * Record a batch of inventory counts for the given store.
     *
     * Persists a snapshot row per item to `inventory_counts` and
     * upserts the matching `store_items` row so the application
     * keeps a single source of truth for the current quantity.
     *
     * For limited users, the snapshot is attributed to the parent
     * (admin) account so the row appears in the data the admin owns.
     * `created_by` keeps the actual user who entered the count.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function recordCounts(User $user, Store $store, array $rows): void
    {
        $now = Carbon::now();
        $owner = $user->isAdmin() ? $user : $this->resolveOwner($user);

        DB::transaction(function () use ($user, $owner, $store, $rows, $now): void {
            foreach ($rows as $row) {
                $payload = Typer::assertArray($row);
                $itemId = Typer::parseInt($payload['item_id'] ?? 0);
                $quantity = Typer::parseInt($payload['quantity'] ?? 0);
                $note = Typer::parseNullableString($payload['note'] ?? null);

                if ($itemId <= 0) {
                    continue;
                }

                $itemQuery = Item::query();
                Item::scopeForUser($itemQuery, $owner);
                $item = $itemQuery->whereKey($itemId)->first();

                if (!$item instanceof Item) {
                    continue;
                }

                InventoryCount::query()->create([
                    'user_id' => $owner->getKey(),
                    'store_id' => $store->getKey(),
                    'item_id' => $item->getKey(),
                    'quantity' => $quantity,
                    'counted_at' => $now,
                    'created_by' => $user->getKey(),
                    'note' => $note,
                ]);

                StoreItem::query()->updateOrCreate(
                    ['store_id' => $store->getKey(), 'item_id' => $item->getKey()],
                    ['quantity' => $quantity],
                );
            }
        });
    }

    /**
     * Latest count for a given store and item, if any.
     */
    public function latestCountForItem(Store $store, Item $item): InventoryCount|null
    {
        $query = InventoryCount::query();
        InventoryCount::scopeForStore($query, $store->getKey());
        InventoryCount::scopeForItem($query, $item->getKey());

        return $query->orderByDesc('counted_at')->orderByDesc('id')->first();
    }

    /**
     * Current quantity on hand for the given store and item.
     */
    public function currentQuantity(Store $store, Item $item): int
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
     * Sum of negative `quantity_difference` from outgoing movements
     * whose source store matches, taken within the last $days days.
     *
     * @return array{quantity: int, per_day: float}
     */
    public function consumptionLastDays(Store $store, Item $item, int $days = self::CONSUMPTION_WINDOW_DAYS): array
    {
        $since = Carbon::now()->subDays($days);

        $movementQuery = StockMovement::query();
        StockMovement::scopeForUser($movementQuery, $store->getUserId());
        StockMovement::scopeOfType($movementQuery, StockMovementTypeEnum::OUTGOING);
        $movementQuery->where('source_store_id', $store->getKey());
        StockMovement::scopeFromDate($movementQuery, $since->toDateTimeString());

        $total = StockMovementItem::query()
            ->whereIn('stock_movement_id', $movementQuery->select('id'))
            ->where('item_id', $item->getKey())
            ->sum('quantity_difference');

        $raw = (float) (string) $total;
        $consumed = $raw < 0.0 ? (int) \floor(-$raw) : 0;
        $perDay = $days > 0 ? $consumed / $days : 0.0;

        return [
            'quantity' => $consumed,
            'per_day' => $perDay,
        ];
    }

    /**
     * Forecast when the store will run out of an item based on the
     * configured consumption window.
     *
     * @return array{current: int, per_day: float, days_left: int|null, status: string}
     */
    public function predictedRunOut(Store $store, Item $item, int $days = self::CONSUMPTION_WINDOW_DAYS): array
    {
        $current = $this->currentQuantity($store, $item);
        $consumption = $this->consumptionLastDays($store, $item, $days);
        $perDay = $consumption['per_day'];

        if ($current <= 0) {
            return [
                'current' => $current,
                'per_day' => $perDay,
                'days_left' => 0,
                'status' => self::STATUS_OUT,
            ];
        }

        if ($perDay <= 0.0) {
            return [
                'current' => $current,
                'per_day' => $perDay,
                'days_left' => null,
                'status' => self::STATUS_NO_DATA,
            ];
        }

        $daysLeft = (int) \floor($current / $perDay);
        $status = $daysLeft <= self::SOON_THRESHOLD_DAYS ? self::STATUS_SOON : self::STATUS_OK;

        return [
            'current' => $current,
            'per_day' => $perDay,
            'days_left' => $daysLeft,
            'status' => $status,
        ];
    }

    /**
     * Build a per-item view of the selected store's inventory,
     * including the current count, average daily consumption, and a
     * predicted status used by the UI.
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

        $latestCounts = InventoryCount::query()
            ->where('store_id', $store->getKey())
            ->orderByDesc('counted_at')
            ->orderByDesc('id')
            ->get()
            ->keyBy(static fn(InventoryCount $count): int => $count->getItemId());

        $currentByItem = StoreItem::query()
            ->where('store_id', $store->getKey())
            ->get()
            ->keyBy(static fn(StoreItem $row): int => $row->getItemId());

        $statusOrder = [
            self::STATUS_OUT => 0,
            self::STATUS_SOON => 1,
            self::STATUS_OK => 2,
            self::STATUS_NO_DATA => 3,
        ];

        $rows = [];

        foreach ($items as $item) {
            $itemId = $item->getKey();
            $storeItem = $currentByItem->get($itemId);
            $current = $storeItem instanceof StoreItem ? $storeItem->getQuantity() : 0;
            $consumption = $this->consumptionLastDays($store, $item);
            $perDay = $consumption['per_day'];
            $daysLeft = null;
            $status = self::STATUS_NO_DATA;

            if ($current <= 0) {
                $daysLeft = 0;
                $status = self::STATUS_OUT;
            } elseif ($perDay > 0.0) {
                $daysLeft = (int) \floor($current / $perDay);
                $status = $daysLeft <= self::SOON_THRESHOLD_DAYS ? self::STATUS_SOON : self::STATUS_OK;
            }

            $latest = $latestCounts->get($itemId);
            $rows[] = [
                'item_id' => $itemId,
                'title' => $item->getTitle(),
                'sku' => $item->getSku(),
                'unit' => $item->getUnit(),
                'current' => $current,
                'latest_count_at' => $latest instanceof InventoryCount ? $latest->getCountedAt()->toDateTimeString() : null,
                'avg_daily_consumption' => $perDay,
                'days_until_restock' => $daysLeft,
                'status' => $status,
            ];
        }

        \usort($rows, static function (array $a, array $b) use ($statusOrder): int {
            $aRank = $statusOrder[$a['status']];
            $bRank = $statusOrder[$b['status']];

            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            $aDays = $a['days_until_restock'] ?? \PHP_INT_MAX;
            $bDays = $b['days_until_restock'] ?? \PHP_INT_MAX;

            return $aDays <=> $bDays;
        });

        // Attach per-item sparkline for the chart column.
        foreach ($rows as $index => $row) {
            $rows[$index]['sparkline'] = $this->sparklineForItem($user, $store, Item::query()->whereKey($row['item_id'])->firstOrFail(), 30);
        }

        return $rows;
    }

    /**
     * Build a chronological list of stock-count snapshots for the user,
     * optionally restricted to a single store and/or item.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historyForUser(User $user, Store $store, Item|null $item, Carbon $from, Carbon $to, int $limit): array
    {
        $query = InventoryCount::query();
        InventoryCount::scopeForUser($query, $user);
        InventoryCount::scopeForStore($query, $store->getKey());
        InventoryCount::scopeBetween($query, $from, $to);

        if ($item instanceof Item) {
            InventoryCount::scopeForItem($query, $item->getKey());
        }

        $counts = $query
            ->orderByDesc('counted_at')
            ->orderByDesc('id')
            ->take($limit)
            ->get();

        $creatorIds = $counts->pluck('created_by')->unique()->all();
        $itemIds = $counts->pluck('item_id')->unique()->all();

        $creators = User::query()
            ->whereIn('id', $creatorIds)
            ->get()
            ->keyBy(static fn(User $u): int => $u->getKey());

        $items = Item::query()
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy(static fn(Item $i): int => $i->getKey());

        return $counts->map(static function (InventoryCount $count) use ($creators, $items): array {
            $item = $items->get($count->getItemId());
            $creator = $creators->get($count->getCreatedBy());

            return [
                'id' => $count->getKey(),
                'item_id' => $count->getItemId(),
                'item_title' => $item instanceof Item ? $item->getTitle() : null,
                'store_id' => $count->getStoreId(),
                'quantity' => $count->getQuantity(),
                'counted_at' => $count->getCountedAt()->toJSON(),
                'note' => $count->getNote(),
                'created_by' => $count->getCreatedBy(),
                'created_by_email' => $creator instanceof User ? $creator->getEmail() : null,
            ];
        })->all();
    }

    /**
     * Build a dense day-by-day sparkline of stock counts for the given
     * store/item pair over the last `$days` days.
     *
     * Days without a recorded snapshot are returned as `null` so the UI
     * can render a gap (the count is unknown for that day).
     *
     * @return array<int, array{label: string, value: int|null}>
     */
    public function sparklineForItem(User $user, Store $store, Item $item, int $days = 30): array
    {
        $today = Carbon::now()->endOfDay();
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        $query = InventoryCount::query();
        InventoryCount::scopeForUser($query, $user);
        InventoryCount::scopeForStore($query, $store->getKey());
        InventoryCount::scopeForItem($query, $item->getKey());
        InventoryCount::scopeSince($query, $from);

        $byDay = [];

        foreach ($query->orderBy('counted_at')->get() as $count) {
            // Latest count of the day wins.
            $byDay[$count->getCountedAt()->toDateString()] = $count->getQuantity();
        }

        $sparkline = [];
        $cursor = $from->copy();

        while ($cursor->lessThanOrEqualTo($today)) {
            $key = $cursor->toDateString();
            $sparkline[] = [
                'label' => $key,
                'value' => $byDay[$key] ?? null,
            ];
            $cursor->addDay();
        }

        return $sparkline;
    }

    /**
     * The admin (parent) account that owns the inventory data for a
     * limited user. Falls back to the user itself when the parent is
     * missing.
     */
    private function resolveOwner(User $user): User
    {
        $parentId = $user->getParentUserId();

        if ($parentId !== null) {
            $parent = User::query()->whereKey($parentId)->first();

            if ($parent instanceof User) {
                return $parent;
            }
        }

        return $user;
    }
}
