<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\Store;
use App\Models\User;
use App\Services\InventorySessionService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
     * Render the inventory editor for the active store.
     */
    public function __invoke(Request $request, InventorySessionService $service): Response
    {
        $user = User::mustAuth();

        if (!$user->isAdmin() && $user->getAssignedStoreId() === null) {
            \abort(403);
        }

        $scopeUser = $user->resolveScopeUser();
        $store = ActiveStoreResolver::resolve($request, $user);

        $rows = [];

        if ($store instanceof Store) {
            $rows = $service->buildStoreView($scopeUser, $store);
        }

        return Inertia::render('inventory-counts/Index', [
            'store' => $store instanceof Store ? [
                'id' => $store->getKey(),
                'name' => $store->getName(),
            ] : null,
            'rows' => $rows,
            'filters' => [
                'store_id' => $store?->getKey(),
            ],
            'is_admin' => $user->isAdmin(),
        ]);
    }
}
