<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Store;

use App\Domain\Stores\StoreManagementService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\StoreValidity;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;

class StoreCreateController
{
    use ValidatesWebRequests;

    /**
     * Show the create store form.
     */
    public function create(): Response
    {
        return Inertia::render('stores/Create');
    }

    /**
     * Persist a new store.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = User::mustAuth();
        $storeValidity = StoreValidity::inject($user->getKey());

        $validated = $this->validateRequest($request, [
            'name' => $storeValidity->name()->required()->toArray(),
            'address' => $storeValidity->address()->nullable()->toArray(),
            'status' => $storeValidity->status()->required()->toArray(),
            'notes' => $storeValidity->notes()->nullable()->toArray(),
            'slack_channel' => $storeValidity->slackChannel()->nullable()->toArray(),
            'is_warehouse' => $storeValidity->isWarehouse()->nullable()->toArray(),
        ]);

        $slackChannel = \mb_trim($validated->assertNullableString('slack_channel') ?? '');

        $store = (new StoreManagementService())->createStore(
            $user,
            $validated->assertString('name'),
            $validated->assertNullableString('address'),
            $validated->assertString('status'),
            $validated->assertNullableString('notes'),
            $slackChannel === '' ? null : $slackChannel,
            $validated->parseBool('is_warehouse'),
        );

        Inertia::flash('success', \__('Store created.'));

        return Resolver::resolveRedirector()->route('stores.show', $store->getKey());
    }
}
