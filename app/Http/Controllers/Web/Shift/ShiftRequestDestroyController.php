<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Domain\Workforce\ShiftRequestService;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftRequestDestroyController
{
    /**
     * Delete a request in the explicitly selected store.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);
        if (!$store instanceof Store) {
            \abort(404);
        }
        (new ShiftRequestService())->deleteRequest($admin, $store, Typer::parseInt($request->route('shiftRequest')));
        Inertia::flash('success', \__('Shift request deleted.'));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'store_id' => $store->getKey(),
            'month' => $request->query('month'),
            'year' => $request->query('year'),
        ]);
    }
}
