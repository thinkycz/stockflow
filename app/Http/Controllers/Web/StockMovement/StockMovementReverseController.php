<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\StockMovement;

use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockMovementService;
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
    public function __invoke(Request $request, StockMovement $stockMovement, StockMovementService $service): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $payload = Typer::assertArray($validated);
        $service->reverseMovement(
            $stockMovement,
            User::mustAuth(),
            Typer::assertString($payload['reason']),
        );

        Inertia::flash('success', \__('Stock movement reversed.'));

        return Resolver::resolveRedirector()->route('stock-movements.index');
    }
}
