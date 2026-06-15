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
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;

class StoreEditController
{
    use ValidatesWebRequests;

    /**
     * Show the edit form.
     */
    public function edit(Store $store): Response
    {
        return Inertia::render('stores/Edit', [
            'store' => [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'address' => $store->getAddress(),
                'status' => $store->getStatus()->value,
                'notes' => $store->getNotes(),
                'is_warehouse' => $store->isWarehouse(),
            ],
        ]);
    }

    /**
     * Persist store updates.
     */
    public function update(Request $request, Store $store): RedirectResponse
    {
        $user = User::mustAuth();
        $storeValidity = StoreValidity::inject($user->getKey());

        $validated = $this->validateRequest($request, [
            'name' => $storeValidity->name()->required()->toArray(),
            'address' => $storeValidity->address()->nullable()->toArray(),
            'status' => $storeValidity->status()->required()->toArray(),
            'notes' => $storeValidity->notes()->nullable()->toArray(),
            'is_warehouse' => $storeValidity->isWarehouse()->nullable()->toArray(),
        ]);

        $store->update([
            'name' => $validated->assertString('name'),
            'address' => $validated->assertNullableString('address'),
            'status' => $validated->assertString('status'),
            'notes' => $validated->assertNullableString('notes'),
            'is_warehouse' => $validated->parseBool('is_warehouse'),
        ]);

        Inertia::flash('success', \__('Store updated.'));

        return Resolver::resolveRedirector()->route('stores.show', $store->getKey());
    }
}
