<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Http\Controllers\Web\Concerns\ResolvesDefaultStore;
use App\Http\Controllers\Web\Concerns\ResolvesUserScope;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryCountHistoryController
{
    use ResolvesDefaultStore;
    use ResolvesUserScope;

    /**
     * Page size hint required by the web index controller architecture test.
     *
     * The history page is bounded by recent snapshots and a configurable
     * date range; pagination is not exposed.
     */
    public const int TAKE = 1000;

    /**
     * Default lookback window for the history page.
     */
    public const int DEFAULT_HISTORY_DAYS = 90;

    /**
     * Render the stock-count history page.
     */
    public function __invoke(Request $request, InventorySessionService $service): Response
    {
        $user = User::mustAuth();

        if (!$user->isAdmin() && $user->getAssignedStoreId() === null) {
            \abort(403);
        }

        $storesQuery = Store::query();
        Store::scopeForUser($storesQuery, $this->resolveScopeUser($user));
        $stores = $storesQuery->orderBy('name')->get()->all();

        $itemsQuery = Item::query();
        Item::scopeForUser($itemsQuery, $this->resolveScopeUser($user));
        $items = $itemsQuery->orderBy('title')->get()->all();

        $defaultStore = $this->resolveDefaultStore($stores, $user);
        $requestedStoreId = Typer::parseNullableInt($request->query('store_id'));
        $storeId = $requestedStoreId ?? $defaultStore?->getKey();

        $this->enforceStoreScope($user, $storeId, $stores);

        $store = null;

        if ($storeId !== null) {
            $storeLookup = Store::query();
            Store::scopeForUser($storeLookup, $this->resolveScopeUser($user));
            $store = $storeLookup->whereKey($storeId)->first();
        }

        $itemId = Typer::parseNullableInt($request->query('item_id'));
        $item = null;

        if ($itemId !== null) {
            $itemLookup = Item::query();
            Item::scopeForUser($itemLookup, $this->resolveScopeUser($user));
            $item = $itemLookup->whereKey($itemId)->first();
        }

        $now = Carbon::now();
        $fromParam = Typer::parseNullableString($request->query('from'));
        $toParam = Typer::parseNullableString($request->query('to'));

        $from = $fromParam !== null ? Carbon::parse($fromParam)->startOfDay() : $now->copy()->subDays(self::DEFAULT_HISTORY_DAYS)->startOfDay();
        $to = $toParam !== null ? Carbon::parse($toParam)->endOfDay() : $now->copy()->endOfDay();

        $rows = [];

        if ($store instanceof Store) {
            $rows = $service->historyForUser($this->resolveScopeUser($user), $store, $item, $from, $to, self::TAKE);
        }

        return Inertia::render('inventory-counts/History', [
            'store' => $store instanceof Store ? [
                'id' => $store->getKey(),
                'name' => $store->getName(),
            ] : null,
            'stores' => \array_map(static fn(Store $store): array => $store->toSelectOption(), $stores),
            'items' => \array_map(static fn(Item $item): array => [
                'id' => $item->getKey(),
                'title' => $item->getTitle(),
            ], $items),
            'rows' => $rows,
            'filters' => [
                'store_id' => $store?->getKey(),
                'item_id' => $item?->getKey(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'is_admin' => $user->isAdmin(),
        ]);
    }

    /**
     * Block access to a store the user is not entitled to view.
     *
     * @param array<int, Store> $stores
     */
    private function enforceStoreScope(User $user, int|null $storeId, array $stores): void
    {
        if ($storeId === null) {
            return;
        }

        if ($user->isAdmin()) {
            return;
        }

        $assignedStoreId = $user->getAssignedStoreId();

        if ($assignedStoreId !== null && $assignedStoreId === $storeId) {
            return;
        }

        \abort(403);
    }
}
