<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftValidity;
use App\Models\Store;
use App\Models\User;
use App\Services\ShiftRequestService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftRequestApproveController
{
    use ValidatesWebRequests;

    /**
     * Convert a shift request into a shift.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);

        if (!$store instanceof Store) {
            \abort(404);
        }

        $validity = ShiftValidity::inject($admin->getKey());
        $validated = $this->validateRequest($request, [
            'start_time' => $validity->startTime()->required()->toArray(),
            'end_time' => $validity->endTime()->required()->toArray(),
            'allow_overlap' => $validity->allowOverlap()->nullable()->toArray(),
        ]);

        (new ShiftRequestService())->approve(
            $admin,
            $store,
            Typer::parseInt($request->route('shiftRequest')),
            $validated->assertString('start_time'),
            $validated->assertString('end_time'),
            $validated->parseBool('allow_overlap'),
        );

        Inertia::flash('success', \__('Shift request approved.'));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'month' => $request->query('month'),
            'year' => $request->query('year'),
        ]);
    }
}
