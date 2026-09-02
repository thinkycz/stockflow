<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\StoreValidity;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

/**
 * Persist the admin's choice of active store into the browser session.
 *
 * Limited users cannot switch stores; they are pinned to their assigned
 * store and the request is rejected with a 403 + flash.
 *
 * Returns JSON when the request expects JSON (axios calls from the
 * StoreSwitcher component) so the frontend can reload props without a
 * full Inertia re-mount. Falls back to a redirect for traditional form
 * posts.
 */
class StoreSwitchController
{
    use ValidatesWebRequests;

    /**
     * Switch the active store.
     */
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $user = User::mustAuth();

        if (!$user->isAdmin()) {
            Inertia::flash('error', \__('You do not have permission for this section.'));

            return Resolver::resolveRedirector()->route('dashboard');
        }

        $storeValidity = StoreValidity::inject($user->getKey());

        $validated = $this->validateRequest($request, [
            'store_id' => $storeValidity->id()->required()->toArray(),
        ]);

        $storeId = $validated->parseInt('store_id');

        $store = Store::query()->whereKey($storeId)->first();

        if (!$store instanceof Store || $store->getUserId() !== $user->getKey() || !$store->isActive()) {
            Inertia::flash('error', \__('Selected store is not available.'));

            return Resolver::resolveRedirector()->back();
        }

        $request->session()->put(ActiveStoreResolver::SESSION_KEY, $store->getKey());

        if ($request->expectsJson()) {
            return new JsonResponse([
                'active_store' => [
                    'id' => $store->getKey(),
                    'name' => $store->getName(),
                    'is_warehouse' => $store->isWarehouse(),
                ],
            ]);
        }

        return Resolver::resolveRedirector()->back();
    }
}
