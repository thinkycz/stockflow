<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Domain\Workforce\WorkforceManagementService;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftDestroyController
{
    /**
     * Delete a shift.
     */
    public function __invoke(Request $request, Shift $shift): RedirectResponse
    {
        (new WorkforceManagementService())->deleteShift(
            User::mustAuth(),
            Typer::assertInstance(Store::query()->whereKey($shift->getStoreId())->firstOrFail(), Store::class),
            $shift,
        );

        Inertia::flash('success', \__('Shift deleted.'));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'month' => $request->query('month'),
            'year' => $request->query('year'),
        ]);
    }
}
