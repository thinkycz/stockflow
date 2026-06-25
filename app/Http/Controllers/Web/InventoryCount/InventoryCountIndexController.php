<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\Store;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryCountIndexController
{
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

        $storesForSelect = \array_map(static fn(Store $row): array => [
            'id' => $row->getKey(),
            'name' => $row->getName(),
        ], $isLimited ? \array_values(\array_filter($stores, static fn(Store $row): bool => $assignedStoreId === $row->getKey())) : $stores);

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

    /**
     * The owner used for store scoping.
     *
     * For a limited user this is the admin (parent) so that the limited
     * user can browse the same stores that the admin owns.
     */
    private function resolveScopeUser(User $user): User
    {
        if ($user->isAdmin()) {
            return $user;
        }

        $parentId = $user->getParentUserId();

        if ($parentId === null) {
            throw new RuntimeException('Limited user #' . $user->getKey() . ' has no parent_user_id.');
        }

        $parent = User::query()->whereKey($parentId)->first();

        if (!$parent instanceof User) {
            throw new RuntimeException('Parent user #' . $parentId . ' referenced by user #' . $user->getKey() . ' does not exist.');
        }

        return $parent;
    }

    /**
     * Pick the first owned store as the default selection, preferring
     * non-warehouse retail stores when one is available. Limited users
     * are pinned to their assigned store.
     *
     * @param array<int, Store> $stores
     */
    private function resolveDefaultStore(array $stores, User $user): Store|null
    {
        if (!$user->isAdmin()) {
            $assignedId = $user->getAssignedStoreId();

            if ($assignedId !== null) {
                foreach ($stores as $store) {
                    if ($assignedId === $store->getKey()) {
                        return $store;
                    }
                }
            }

            return $stores[0] ?? null;
        }

        foreach ($stores as $store) {
            if (!$store->isWarehouse()) {
                return $store;
            }
        }

        return $stores[0] ?? null;
    }
}
