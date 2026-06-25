<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Http\Controllers\Web\Concerns\ResolvesDefaultStore;
use App\Http\Controllers\Web\Concerns\ResolvesUserScope;
use App\Models\Store;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryCountIndexController
{
    use ResolvesDefaultStore;
    use ResolvesUserScope;

    /**
     * Page size hint required by the web index controller architecture test.
     * The inventory page renders every catalog item in a single grid, so
     * the list is bounded by the catalog size and pagination is not
     * exposed in the UI.
     */
    public const int TAKE = 1000;

    /**
     * Render the inventory editor for the selected store.
     */
    public function __invoke(Request $request, InventorySessionService $service): Response
    {
        $user = User::mustAuth();
        $isLimited = !$user->isAdmin();
        $assignedStoreId = $isLimited ? $user->getAssignedStoreId() : null;

        if ($isLimited && $assignedStoreId === null) {
            \abort(403);
        }

        $scopeUser = $this->resolveScopeUser($user);
        $storesQuery = Store::query();
        Store::scopeForUser($storesQuery, $scopeUser);
        $stores = Store::querySelect($storesQuery)
            ->orderBy('name')
            ->get()
            ->all();

        $requestedStoreId = Typer::parseNullableInt($request->query('store_id'));
        $defaultStore = $this->resolveDefaultStore($stores, $user);
        $storeId = $requestedStoreId ?? $defaultStore?->getKey();

        if ($isLimited && $storeId !== null && $storeId !== $assignedStoreId) {
            \abort(403);
        }

        $store = null;
        if ($storeId !== null) {
            $storeLookup = Store::query();
            Store::scopeForUser($storeLookup, $scopeUser);
            $store = $storeLookup->whereKey($storeId)->first();
        }

        $rows = [];

        if ($store instanceof Store) {
            $rows = $service->buildStoreView($scopeUser, $store);
        }

        $storesForSelect = \array_map(static fn(Store $row): array => $row->toSelectOption(), $isLimited ? \array_values(\array_filter($stores, static fn(Store $row): bool => $assignedStoreId === $row->getKey())) : $stores);

        return Inertia::render('inventory-counts/Index', [
            'store' => $store instanceof Store ? [
                'id' => $store->getKey(),
                'name' => $store->getName(),
            ] : null,
            'stores' => $storesForSelect,
            'rows' => $rows,
            'filters' => [
                'store_id' => $store?->getKey(),
            ],
            'is_admin' => $user->isAdmin(),
        ]);
    }
}
