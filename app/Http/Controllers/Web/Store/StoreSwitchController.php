<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\StoreValidity;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

/**
 * Persist the admin's choice of active store into the session.
 *
 * Limited users cannot switch stores; they are pinned to their assigned
 * store and the request is rejected with a 403 + flash.
 */
class StoreSwitchController
{
    use ValidatesWebRequests;

    /**
     * Switch the active store.
     */
    public function __invoke(Request $request): RedirectResponse
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

        if (!$store instanceof Store || $store->getUserId() !== $user->getKey()) {
            Inertia::flash('error', \__('Selected store is not available.'));

            return Resolver::resolveRedirector()->back();
        }

        $request->session()->put('active_store_id', $store->getKey());

        return Resolver::resolveRedirector()->back();
    }
}
