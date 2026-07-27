<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\Store;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryDraftStartController
{
    /**
     * Start or resume the active inventory draft for a store.
     */
    public function __invoke(Request $request, InventorySessionService $service): RedirectResponse
    {
        $store = Store::query()->whereKey(Typer::parseInt($request->input('store_id')))->firstOrFail();
        $service->startDraft(User::mustAuth(), $store);

        return Resolver::resolveRedirector()->route('inventory-counts.index');
    }
}
