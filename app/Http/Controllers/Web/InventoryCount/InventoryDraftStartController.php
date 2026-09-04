<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Domain\Inventory\ManageInventory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryDraftStartController
{
    /**
     * Start or resume the active inventory draft for a store.
     */
    public function __invoke(Request $request, ManageInventory $operation): RedirectResponse
    {
        $operation->startDraft(User::mustAuth(), Typer::parseInt($request->input('store_id')));

        return Resolver::resolveRedirector()->route('inventory-counts.index');
    }
}
