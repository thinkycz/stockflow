<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Domain\Inventory\ManageInventory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryCountUpdateController
{
    /**
     * Persist a batch of inventory counts for the selected store.
     */
    public function __invoke(Request $request, ManageInventory $operation): RedirectResponse
    {
        $user = User::mustAuth();
        $session = $operation->createCount($user, Typer::assertStringKeyArray($request->all()));

        Inertia::flash('success', \__('Inventory count saved.'));

        return Resolver::resolveRedirector()->route('inventory-counts.show', [
            'session' => $session->getKey(),
        ]);
    }
}
