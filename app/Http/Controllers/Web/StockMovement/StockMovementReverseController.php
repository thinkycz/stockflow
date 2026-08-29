<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\StockMovement;

use App\Models\StockMovement;
use App\Models\User;
use App\Operations\Inventory\ManageInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class StockMovementReverseController
{
    /**
     * Create an immutable compensating movement.
     */
    public function __invoke(Request $request, StockMovement $stockMovement, ManageInventory $operation): RedirectResponse
    {
        $operation->reverseMovement(
            User::mustAuth(),
            $stockMovement->getKey(),
            Typer::assertStringKeyArray($request->all()),
        );

        Inertia::flash('success', \__('Stock movement reversed.'));

        return Resolver::resolveRedirector()->route('stock-movements.index');
    }
}
