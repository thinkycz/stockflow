<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Enums\RemovalOutcomeEnum;
use App\Models\Store;
use App\Models\User;
use App\Services\AdministrationManagementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;

class StoreDestroyController
{
    /**
     * Delete a store. Blocked when the store has inventory or stock movement history.
     */
    public function __invoke(Store $store): RedirectResponse
    {
        $outcome = (new AdministrationManagementService())->deleteStore(User::mustAuth(), $store);

        if ($outcome === RemovalOutcomeEnum::BLOCKED) {
            Thrower::default()->message('store', \__('Resolve store assignments, stock, and active operational work before removing this store.'))->throw();
        }

        Inertia::flash('success', $outcome === RemovalOutcomeEnum::ARCHIVED
            ? \__('Store deactivated to preserve its history.')
            : \__('Store deleted.'));

        return Resolver::resolveRedirector()->route('stores.index');
    }
}
