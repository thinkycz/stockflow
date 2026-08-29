<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\User;
use App\Operations\Inventory\ManageInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryDraftCloseController
{
    /**
     * Atomically close an inventory draft and post its reconciliation.
     */
    public function __invoke(Request $request, ManageInventory $operation): RedirectResponse
    {
        $user = User::mustAuth();
        $session = $operation->closeDraft(
            $user,
            Typer::parseInt($request->route('session')),
            Typer::assertStringKeyArray($request->all()),
        );
        Inertia::flash('success', \__('Inventory count saved.'));

        return Resolver::resolveRedirector()->route('inventory-counts.show', ['session' => $session->getKey()]);
    }
}
