<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Domain\Inventory\ManageInventory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryDraftCancelController
{
    /**
     * Cancel an open draft without changing stock.
     */
    public function __invoke(Request $request, ManageInventory $operation): RedirectResponse
    {
        $operation->cancelDraft(User::mustAuth(), Typer::parseInt($request->route('session')));

        return Resolver::resolveRedirector()->route('inventory-counts.index');
    }
}
