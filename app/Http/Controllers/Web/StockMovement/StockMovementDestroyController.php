<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\StockMovement;

use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class StockMovementDestroyController
{
    /**
     * Delete a stock movement and reverse its inventory changes.
     */
    public function __invoke(StockMovement $stockMovement, StockMovementService $service): RedirectResponse
    {
        $service->deleteMovement($stockMovement);

        Inertia::flash('success', \__('Stock movement deleted.'));

        return Resolver::resolveRedirector()->route('stock-movements.index');
    }
}
