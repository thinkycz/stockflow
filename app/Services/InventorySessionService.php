<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OperationalActivityTypeEnum;
use App\Enums\StockMovementClassificationEnum;
use App\Enums\StockMovementTypeEnum;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection;
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
     * Start or return the single active draft for a store.
     */
    public function startDraft(User $user, Store $store): InventorySession
    {
        $owner = $user->resolveScopeUser();
        $this->authoriseStore($user, $store);

        return DB::transaction(function () use ($user, $owner, $store): InventorySession {
            $existing = InventorySession::query()
                ->where('user_id', $owner->getKey())
                ->where('active_store_key', $store->getKey())
                ->lockForUpdate()
                ->first();

            if ($existing instanceof InventorySession) {
                return $existing;
            }

            $snapshot = StoreItem::query()
                ->where('store_id', $store->getKey())
                ->orderBy('item_id')
                ->pluck('quantity', 'item_id')
                ->map(static fn(mixed $quantity): string => BigDecimal::of((string) Typer::assertScalar($quantity))->toScale(3)->__toString())
                ->all();

            return InventorySession::query()->create([
                'user_id' => $owner->getKey(),
                'store_id' => $store->getKey(),
                'active_store_key' => $store->getKey(),
                'created_by' => $user->getKey(),
                'status' => 'draft',
                'started_at' => Carbon::now(),
                'counted_at' => null,
                'opening_snapshot' => $snapshot,
            ]);
        }, 3);
    }

    /**
     * Find the active draft visible to the user.
     */
    public function activeDraft(User $user, Store $store): InventorySession|null
    {
        $this->authoriseStore($user, $store);

        return InventorySession::query()
            ->where('user_id', $user->resolveScopeUser()->getKey())
            ->where('active_store_key', $store->getKey())
            ->with('items')
            ->first();
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public function saveDraftRow(User $user, InventorySession $session, array $payload): InventorySessionItem
    {
        $this->authoriseSession($user, $session);

        return DB::transaction(function () use ($session, $payload): InventorySessionItem {
            $lockedSession = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if (!$lockedSession->isDraft()) {
                $this->fail(['inventory' => \__('Only an open inventory draft can be edited.')]);
            }

            $itemId = Typer::parseInt($payload['item_id'] ?? 0);
            $quantity = $this->draftQuantity($payload['quantity'] ?? -1);
            $clientVersion = Typer::parseInt($payload['client_version'] ?? 0);
            if ($quantity->isNegative() || $clientVersion <= 0) {
                $this->fail(['quantity' => \__('A non-negative quantity and version are required.')]);
            }

            $item = Item::query()->where('user_id', $lockedSession->getUserId())->whereKey($itemId)->first();
            if (!$item instanceof Item) {
                $this->fail(['item_id' => \__('Item not found.')]);
            }

            $existing = InventorySessionItem::query()
                ->where('session_id', $lockedSession->getKey())
                ->where('item_id', $itemId)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof InventorySessionItem && $clientVersion <= Typer::parseInt($existing->getAttribute('client_version'))) {
                return $existing;
            }

            $countedAt = Carbon::now();
            $expected = $this->decimal($this->currentQuantity($lockedSession->getStore(), $item));
            $difference = $quantity->minus($expected);
            $classification = $this->resolveDraftClassification($difference, Typer::parseNullableString($payload['classification'] ?? null));
            $snapshot = Typer::assertArray($lockedSession->getAttribute('opening_snapshot') ?? []);

            return InventorySessionItem::query()->updateOrCreate(
                ['session_id' => $lockedSession->getKey(), 'item_id' => $itemId],
                [
                    'quantity' => (string) $quantity,
                    'counted_at' => $countedAt,
                    'opening_quantity' => (string) $this->decimal($snapshot[(string) $itemId] ?? $snapshot[$itemId] ?? 0),
                    'client_version' => $clientVersion,
                    'expected_quantity' => (string) $expected,
                    'quantity_difference' => (string) $difference,
                    'classification' => $classification?->value,
                    'observation_started_at' => $this->previousCountedAt($lockedSession->getStore(), $item, $countedAt),
                    'note' => Typer::parseNullableString($payload['note'] ?? null),
                ],
            );
        }, 3);
    }

    /**
     * Close a draft and apply only its row-level physical differences.
     */
    public function closeDraft(User $user, InventorySession $session, Carbon|null $countedAt = null): InventorySession
    {
        $this->authoriseSession($user, $session);
        $owner = $user->resolveScopeUser();
        $countedAt ??= Carbon::now();

        return DB::transaction(function () use ($user, $session, $owner, $countedAt): InventorySession {
            $session = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if (!$session->isDraft()) {
                $this->fail(['inventory' => \__('Only an open inventory draft can be closed.')]);
            }

            $rows = $session->items()->with('item')->orderBy('item_id')->lockForUpdate()->get();
            $reconciliationRows = [];
            foreach ($rows as $row) {
                $item = $row->getItem();
                $difference = $this->decimal(Typer::assertScalar($row->getQuantityDifference()));
                $storeItem = StoreItem::query()->firstOrCreate(
                    ['store_id' => $session->getStore()->getKey(), 'item_id' => $item->getKey()],
                    ['quantity' => 0],
                );
                $storeItem = StoreItem::query()->whereKey($storeItem->getKey())->lockForUpdate()->firstOrFail();
                $before = $this->decimal($storeItem->getAttribute('quantity'));
                $after = $before->plus($difference);
                if ($after->isNegative()) {
                    $this->fail(['inventory' => \__('Inventory reconciliation would make stock negative.')]);
                }
                $storeItem->update(['quantity' => (string) $after]);

                $classification = $row->getClassification();
                if (!$difference->isZero() && $classification instanceof StockMovementClassificationEnum) {
                    $reconciliationRows[] = [
                        'session_item' => $row, 'item' => $item, 'expected' => (string) $before, 'counted' => (string) $after,
                        'difference' => (string) $difference, 'classification' => $classification,
                        'observation_started_at' => $row->getObservationStartedAt(),
                    ];
                }
            }

            $now = Carbon::now();
            $session->update(['status' => 'closed', 'active_store_key' => null, 'counted_at' => $countedAt, 'closed_at' => $now]);
            $this->movementService->createInventoryReconciliation($session, $owner, $reconciliationRows);
            $this->notifyInventory($user, $session, $rows->count(), \count($reconciliationRows));

            return $session;
        }, 3);
    }

    /**
     * Cancel a draft without posting inventory changes.
     */
    public function cancelDraft(User $user, InventorySession $session): void
    {
        if (!$user->isAdmin()) {
            \abort(403);
        }
        $this->authoriseSession($user, $session);
        $session->update(['status' => 'cancelled', 'active_store_key' => null, 'cancelled_at' => Carbon::now()]);
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

                $quantity = $this->decimal($payload['quantity']);
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

                $expected = $this->decimal($storeItem->getAttribute('quantity'));
                $difference = $quantity->minus($expected);
                $classification = $this->resolveClassification(
                    $difference,
                    Typer::parseNullableString($payload['classification'] ?? null),
                );
                $observationStartedAt = $this->previousCountedAt($store, $item, $now);

                $sessionItem = InventorySessionItem::query()->create([
                    'session_id' => $session->getKey(),
                    'item_id' => $item->getKey(),
                    'quantity' => (string) $quantity,
                    'expected_quantity' => (string) $expected,
                    'quantity_difference' => (string) $difference,
                    'classification' => $classification?->value,
                    'observation_started_at' => $observationStartedAt,
                    'note' => $rowNote,
                ]);

                $storeItem->update(['quantity' => (string) $quantity]);

                if (!$difference->isZero() && $classification instanceof StockMovementClassificationEnum) {
                    $reconciliationRows[] = [
                        'session_item' => $sessionItem,
                        'item' => $item,
                        'expected' => (string) $expected,
                        'counted' => (string) $quantity,
                        'difference' => (string) $difference,
                        'classification' => $classification,
                        'observation_started_at' => $observationStartedAt,
                    ];
                }
            }

            $this->movementService->createInventoryReconciliation($session, $owner, $reconciliationRows);
            $this->notifyInventory($user, $session, $session->items()->count(), \count($reconciliationRows));

            return $session;
        });
    }

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
    public function consumptionForItems(Store $store, array $itemIds, int $days = self::CONSUMPTION_WINDOW_DAYS, int $maximumIntervals = self::MAXIMUM_INTERVALS): array
    {
        $since = Carbon::now()->subDays($days)->startOfDay();
        $intervals = DB::table('inventory_session_items')
            ->join('inventory_sessions', 'inventory_sessions.id', '=', 'inventory_session_items.session_id')
            ->where('inventory_sessions.user_id', $store->getUserId())
            ->where('inventory_sessions.store_id', $store->getKey())
            ->where('inventory_sessions.status', 'closed')
            ->whereIn('inventory_session_items.item_id', $itemIds)
            ->whereNotNull('inventory_session_items.observation_started_at')
            ->where('inventory_sessions.counted_at', '>=', $since->toDateTimeString())
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
        $previousByItem = [];
        $previousRows = DB::table('inventory_session_items')
            ->join('inventory_sessions', 'inventory_sessions.id', '=', 'inventory_session_items.session_id')
            ->where('inventory_sessions.store_id', $store->getKey())
            ->where('inventory_sessions.status', 'closed')
            ->orderByDesc('inventory_sessions.counted_at')
            ->orderByDesc('inventory_session_items.id')
            ->get(['inventory_session_items.item_id', 'inventory_session_items.quantity']);
        foreach ($previousRows as $previousRow) {
            $itemId = Typer::parseInt($previousRow->item_id);
            if (!isset($previousByItem[$itemId])) {
                $previousByItem[$itemId] = $this->number($previousRow->quantity);
            }
        }

        $rows = [];

        foreach ($items as $item) {
            $itemId = $item->getKey();
            $storeItem = $currentByItem->get($itemId);
            $current = $storeItem instanceof StoreItem ? $storeItem->getQuantity() : 0;
            $previous = $previousByItem[$itemId] ?? null;

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
     * quantity recorded in the session and the previous quantity from
     * the prior session for the same store/item (null if none).
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildSessionView(User $user, InventorySession $session): array
    {
        $itemsQuery = Item::query();
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
     * Dispatch one finalized inventory activity without duplicating its reconciliation.
     */
    private function notifyInventory(User $user, InventorySession $session, int $countedRows, int $differenceRows): void
    {
        OperationalActivityService::dispatch(
            OperationalActivityTypeEnum::INVENTORY_SAVED,
            $user,
            Carbon::now('UTC')->toIso8601String(),
            Resolver::resolveUrlGenerator()->route('inventory-counts.show', ['session' => $session->getKey()]),
            [['store' => $session->getStore(), 'perspective' => null]],
            [
                'Slack inventory number' => '#' . $session->getKey(),
                'Slack counted rows' => (string) $countedRows,
                'Slack difference rows' => (string) $differenceRows,
            ],
        );
    }

    /**
     * @param array{quantity: float|int, per_day: float, coverage_days: float} $consumption
     *
     * @return array{current: float|int, per_day: float, coverage_days: float, days_left: int|null, projected_stockout_at: string|null, status: string}
     */
    private function predictionFromConsumption(float|int $current, array $consumption): array
    {
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
     * Enforce owner and assigned-store access.
     */
    private function authoriseStore(User $user, Store $store): void
    {
        if ($store->getUserId() !== $user->resolveScopeUser()->getKey()) {
            \abort(404);
        }

        if (!$user->isAdmin() && $user->getAssignedStoreId() !== $store->getKey()) {
            \abort(403);
        }
    }

    /**
     * Enforce access to the draft's store and owning company.
     */
    private function authoriseSession(User $user, InventorySession $session): void
    {
        if ($session->getUserId() !== $user->resolveScopeUser()->getKey()) {
            \abort(404);
        }

        $this->authoriseStore($user, $session->getStore());
    }

    /**
     * @param array<string, array<array-key, mixed>|string> $messages
     */
    private function fail(array $messages): never
    {
        $validator = Resolver::resolveValidatorFactory()->make([], []);
        $thrower = new Thrower($validator);

        foreach ($messages as $key => $message) {
            $thrower->message($key, $message);
        }

        $thrower->throw();
    }

    /**
     * Resolve and validate a classification for an inventory difference.
     */
    private function resolveClassification(BigDecimal $difference, string|null $requested): StockMovementClassificationEnum|null
    {
        if ($difference->isZero()) {
            return null;
        }

        $classification = $requested === null
            ? ($difference->isNegative() ? StockMovementClassificationEnum::CONSUMPTION : StockMovementClassificationEnum::INVENTORY_CORRECTION)
            : StockMovementClassificationEnum::from($requested);

        if (
            ($difference->isNegative() && !$classification->supportsNegativeDifference()) ||
            ($difference->isPositive() && !$classification->supportsPositiveDifference())
        ) {
            $validator = Resolver::resolveValidatorFactory()->make([], []);
            (new Thrower($validator))
                ->message('rows', \__('The selected inventory reason does not match the quantity difference.'))
                ->throw();
        }

        return $classification;
    }

    /**
     * Keep draft autosave non-blocking when the expected stock changed after
     * the browser selected a reason or when a stale client sends an unknown
     * classification.
     */
    private function resolveDraftClassification(BigDecimal $difference, string|null $requested): StockMovementClassificationEnum|null
    {
        if ($difference->isZero()) {
            return null;
        }

        $fallback = $difference->isNegative()
            ? StockMovementClassificationEnum::CONSUMPTION
            : StockMovementClassificationEnum::INVENTORY_CORRECTION;
        $classification = $requested === null
            ? $fallback
            : StockMovementClassificationEnum::tryFrom($requested) ?? $fallback;

        if (
            ($difference->isNegative() && !$classification->supportsNegativeDifference()) ||
            ($difference->isPositive() && !$classification->supportsPositiveDifference())
        ) {
            return $fallback;
        }

        return $classification;
    }

    /**
     * Parse an exact stock quantity at the canonical scale.
     */
    private function decimal(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) Typer::assertScalar($value))->toScale(3, RoundingMode::Unnecessary);
    }

    /**
     * Normalize user-entered draft quantities instead of rejecting harmless
     * precision beyond the canonical three decimal places.
     */
    private function draftQuantity(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) Typer::assertScalar($value))->toScale(3, RoundingMode::HalfUp);
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
