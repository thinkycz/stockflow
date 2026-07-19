<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementClassificationEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
use Thinkycz\LaravelCore\Support\Typer;

class InventorySessionService
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
     * Create the service.
     */
    public function __construct(
        private readonly StockMovementService $movementService,
    ) {
    }

    /**
     * Create a new inventory session for the given store.
     *
     * Persists a session header in `inventory_sessions`, one row per
     * item in `inventory_session_items`, and upserts the matching
     * `store_items` row so the application keeps a single source of
     * truth for the current quantity.
     *
     * Rows whose `quantity` is `null` are skipped entirely: no
     * `inventory_session_items` row is written and the existing
     * `store_items.quantity` is left untouched. A row with `quantity`
     * of `0` (or any other integer) is persisted as recorded and
     * applied to the on-hand count.
     *
     * For limited users, the session is attributed to the parent
     * (admin) account so the row appears in the data the admin owns.
     * `created_by` keeps the actual user who entered the count.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function createSession(User $user, Store $store, array $rows, string|null $note = null): InventorySession
    {
        $now = Carbon::now();
        $owner = $user->isAdmin() ? $user : $this->resolveOwner($user);

        return DB::transaction(function () use ($user, $owner, $store, $rows, $note, $now): InventorySession {
            $session = InventorySession::query()->create([
                'user_id' => $owner->getKey(),
                'store_id' => $store->getKey(),
                'created_by' => $user->getKey(),
                'counted_at' => $now,
                'note' => $note,
            ]);

            $reconciliationRows = [];

            foreach ($rows as $row) {
                $payload = Typer::assertArray($row);
                $itemId = Typer::parseInt($payload['item_id'] ?? 0);

                if ($itemId <= 0) {
                    continue;
                }

                if (!\array_key_exists('quantity', $payload) || $payload['quantity'] === null) {
                    continue;
                }

                $quantity = Typer::parseInt($payload['quantity']);
                $rowNote = Typer::parseNullableString($payload['note'] ?? null);

                $itemQuery = Item::query();
                Item::scopeForUser($itemQuery, $owner);
                $item = $itemQuery->whereKey($itemId)->first();

                if (!$item instanceof Item) {
                    continue;
                }

                $storeItem = StoreItem::query()->firstOrCreate(
                    ['store_id' => $store->getKey(), 'item_id' => $item->getKey()],
                    ['quantity' => 0],
                );
                $storeItem = StoreItem::query()->whereKey($storeItem->getKey())->lockForUpdate()->first();

                if (!$storeItem instanceof StoreItem) {
                    continue;
                }

                $expected = $storeItem->getQuantity();
                $difference = $quantity - $expected;
                $classification = $this->resolveClassification(
                    $difference,
                    Typer::parseNullableString($payload['classification'] ?? null),
                );
                $observationStartedAt = $this->previousCountedAt($store, $item, $now);

                $sessionItem = InventorySessionItem::query()->create([
                    'session_id' => $session->getKey(),
                    'item_id' => $item->getKey(),
                    'quantity' => $quantity,
                    'expected_quantity' => $expected,
                    'quantity_difference' => $difference,
                    'classification' => $classification?->value,
                    'observation_started_at' => $observationStartedAt,
                    'note' => $rowNote,
                ]);

                $storeItem->update(['quantity' => $quantity]);

                if ($difference !== 0 && $classification instanceof StockMovementClassificationEnum) {
                    $reconciliationRows[] = [
                        'session_item' => $sessionItem,
                        'item' => $item,
                        'expected' => $expected,
                        'counted' => $quantity,
                        'difference' => $difference,
                        'classification' => $classification,
                        'observation_started_at' => $observationStartedAt,
                    ];
                }
            }

            $this->movementService->createInventoryReconciliation($session, $owner, $reconciliationRows);

            return $session;
        });
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
     * Quantity from the most recent prior inventory session for the
     * given store and item. Returns null when no prior session exists.
     */
    public function previousQuantity(Store $store, Item $item, Carbon|null $before = null): int|null
    {
        $query = InventorySessionItem::query()
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

        return $value === null ? null : Typer::parseInt($value);
    }

    /**
     * Timestamp of the most recent prior physical count.
     */
    public function previousCountedAt(Store $store, Item $item, Carbon|null $before = null): Carbon|null
    {
        $query = InventorySessionItem::query()
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
     * @return array{quantity: int, per_day: float, coverage_days: float}
     */
    public function consumptionLastDays(
        Store $store,
        Item $item,
        int $days = self::CONSUMPTION_WINDOW_DAYS,
        int $maximumIntervals = self::MAXIMUM_INTERVALS,
    ): array
    {
        $since = Carbon::now()->subDays($days)->startOfDay();
        $intervals = DB::table('inventory_session_items')
            ->join('inventory_sessions', 'inventory_sessions.id', '=', 'inventory_session_items.session_id')
            ->where('inventory_sessions.user_id', $store->getUserId())
            ->where('inventory_sessions.store_id', $store->getKey())
            ->where('inventory_session_items.item_id', $item->getKey())
            ->whereNotNull('inventory_session_items.observation_started_at')
            ->where('inventory_sessions.counted_at', '>=', $since->toDateTimeString())
            ->orderByDesc('inventory_sessions.counted_at')
            ->limit($maximumIntervals)
            ->get([
                'inventory_sessions.counted_at',
                'inventory_session_items.observation_started_at',
                'inventory_session_items.quantity_difference',
                'inventory_session_items.classification',
            ]);

        $consumed = 0;
        $coverageSeconds = 0;
        $earliest = null;
        $latest = null;

        foreach ($intervals as $interval) {
            $start = Carbon::parse(Typer::assertString($interval->observation_started_at));
            $end = Carbon::parse(Typer::assertString($interval->counted_at));
            $effectiveStart = $start->lessThan($since) ? $since->copy() : $start;

            if ($effectiveStart->greaterThanOrEqualTo($end)) {
                continue;
            }

            $coverageSeconds += (int) $effectiveStart->diffInSeconds($end);
            $earliest = !$earliest instanceof Carbon || $effectiveStart->lessThan($earliest) ? $effectiveStart : $earliest;
            $latest = !$latest instanceof Carbon || $end->greaterThan($latest) ? $end : $latest;

            if (
                StockMovementClassificationEnum::CONSUMPTION->value === Typer::parseNullableString($interval->classification) &&
                Typer::parseInt($interval->quantity_difference) < 0
            ) {
                $consumed += \abs(Typer::parseInt($interval->quantity_difference));
            }
        }

        if ($earliest instanceof Carbon && $latest instanceof Carbon) {
            $manual = DB::table('stock_movement_items')
                ->join('stock_movements', 'stock_movements.id', '=', 'stock_movement_items.stock_movement_id')
                ->where('stock_movements.user_id', $store->getUserId())
                ->where('stock_movements.store_id', $store->getKey())
                ->where('stock_movements.type', StockMovementTypeEnum::CONSUMPTION->value)
                ->where('stock_movement_items.item_id', $item->getKey())
                ->whereBetween('stock_movements.occurred_at', [$earliest->toDateTimeString(), $latest->toDateTimeString()])
                ->sum('stock_movement_items.quantity');
            $consumed += Typer::parseInt($manual);
        }

        $coverageDays = $coverageSeconds / 86400;
        $perDay = $coverageDays >= self::MINIMUM_COVERAGE_DAYS ? $consumed / $coverageDays : 0.0;

        return [
            'quantity' => $consumed,
            'per_day' => $perDay,
            'coverage_days' => $coverageDays,
        ];
    }

    /**
     * Forecast when the store will run out of an item based on the
     * configured consumption window.
     *
     * @return array{current: int, per_day: float, coverage_days: float, days_left: int|null, projected_stockout_at: string|null, status: string}
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
                'coverage_days' => $consumption['coverage_days'],
                'days_left' => 0,
                'projected_stockout_at' => Carbon::now()->toDateString(),
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
            'projected_stockout_at' => Carbon::now()->addDays($daysLeft)->toDateString(),
            'status' => $status,
        ];
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
            $previous = $this->previousQuantity($store, $item);

            $rows[] = [
                'item_id' => $itemId,
                'title' => $item->getTitle(),
                'sku' => $item->getSku(),
                'unit' => $item->getUnit(),
                'current' => $current,
                'previous' => $previous,
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
     * quantity recorded in the session and the previous quantity from
     * the prior session for the same store/item (null if none).
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildSessionView(User $user, InventorySession $session): array
    {
        $itemsQuery = Item::query();
        Item::scopeForUser($itemsQuery, $user);
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
                'expected' => $sessionItem->getExpectedQuantity(),
                'difference' => $sessionItem->getQuantityDifference(),
                'classification' => $sessionItem->getClassification()?->value,
                'previous' => $this->previousQuantity($session->getStore(), $item, $session->getCountedAt()),
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
     * @return array<int, array{label: string, value: int|null}>
     */
    public function sparklineForItem(User $user, Store $store, Item $item, int $days = 30): array
    {
        $today = Carbon::now()->endOfDay();
        $from = Carbon::now()->subDays($days - 1)->startOfDay();

        $records = DB::table('inventory_session_items')
            ->join('inventory_sessions', 'inventory_sessions.id', '=', 'inventory_session_items.session_id')
            ->where('inventory_sessions.user_id', $user->getKey())
            ->where('inventory_sessions.store_id', $store->getKey())
            ->where('inventory_session_items.item_id', $item->getKey())
            ->where('inventory_sessions.counted_at', '>=', $from->toDateTimeString())
            ->orderBy('inventory_sessions.counted_at')
            ->get(['inventory_sessions.counted_at', 'inventory_session_items.quantity']);

        $byDay = [];

        foreach ($records as $record) {
            $countedAt = Carbon::parse(Typer::assertString($record->counted_at));
            $byDay[$countedAt->toDateString()] = Typer::parseInt($record->quantity);
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

    /**
     * Resolve and validate a classification for an inventory difference.
     */
    private function resolveClassification(int $difference, string|null $requested): StockMovementClassificationEnum|null
    {
        if ($difference === 0) {
            return null;
        }

        $classification = $requested === null
            ? ($difference < 0 ? StockMovementClassificationEnum::CONSUMPTION : StockMovementClassificationEnum::INVENTORY_CORRECTION)
            : StockMovementClassificationEnum::from($requested);

        if (
            ($difference < 0 && !$classification->supportsNegativeDifference()) ||
            ($difference > 0 && !$classification->supportsPositiveDifference())
        ) {
            $validator = Resolver::resolveValidatorFactory()->make([], []);
            (new Thrower($validator))
                ->message('rows', \__('The selected inventory reason does not match the quantity difference.'))
                ->throw();
        }

        return $classification;
    }
}
