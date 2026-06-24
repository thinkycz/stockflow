<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\InventoryCountValidity;
use App\Models\Store;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class InventoryCountUpdateController
{
    use ValidatesWebRequests;

    /**
     * Persist a batch of inventory counts for the selected store.
     */
    public function __invoke(Request $request, InventorySessionService $service): RedirectResponse
    {
        $user = User::mustAuth();
        $scopeUser = $user->isAdmin() ? $user : $this->resolveScopeUser($user);
        $validity = InventoryCountValidity::inject($scopeUser->getKey());

        $validated = $this->validateRequest($request, [
            'store_id' => $validity->storeId()->required()->toArray(),
            'rows' => $validity->rows()->required()->toArray(),
            'rows.*.item_id' => $validity->itemId()->required()->toArray(),
            'rows.*.quantity' => $validity->rowQuantity()->required()->toArray(),
            'rows.*.note' => $validity->rowNote()->nullable()->toArray(),
        ]);

        $storeId = $validated->parseInt('store_id');
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $validated->assertArray('rows');

        $storeLookup = Store::query();
        Store::scopeForUser($storeLookup, $scopeUser);
        $store = $storeLookup->whereKey($storeId)->first();

        if (!$store instanceof Store) {
            \abort(404);
        }

        if (!$user->isAdmin()) {
            $assignedStoreId = $user->getAssignedStoreId();

            if ($assignedStoreId === null || $assignedStoreId !== $store->getKey()) {
                \abort(403);
            }
        }

        $session = $service->createSession($user, $store, $rows);

        Inertia::flash('success', \__('Inventory count saved.'));

        return Resolver::resolveRedirector()->route('inventory-counts.show', [
            'session' => $session->getKey(),
        ]);
    }

    /**
     * The owner used for store / item scoping.
     *
     * For a limited user this is the admin (parent) so that the limited
     * user can write inventory counts against stores the admin owns.
     */
    private function resolveScopeUser(User $user): User
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
