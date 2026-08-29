<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Models\Store;
use App\Models\User;
use App\Services\AdministrationManagementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class StoreDestroyController
{
    /**
     * Delete a store. Blocked when the store has inventory or stock movement history.
     */
    public function __invoke(Store $store): RedirectResponse
    {
        (new AdministrationManagementService())->deleteStore(User::mustAuth(), $store);

        Inertia::flash('success', \__('Store deleted.'));

        return Resolver::resolveRedirector()->route('stores.index');
    }
}
