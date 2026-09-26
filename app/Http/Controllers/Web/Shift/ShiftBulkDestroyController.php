<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Domain\Workforce\WorkforceManagementService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftBulkDestroyValidity;
use App\Http\Validation\ShiftRequestValidity;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftBulkDestroyController
{
    use ValidatesWebRequests;

    /**
     * Delete an explicit selection from one store and month.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $validity = new ShiftBulkDestroyValidity();
        $periodValidity = ShiftRequestValidity::inject($admin->getKey());
        $validated = $this->validateRequest($request, [
            'store_id' => $validity->storeId()->required()->toArray(),
            'year' => $periodValidity->year()->required()->toArray(),
            'month' => $periodValidity->month()->required()->toArray(),
            'shift_ids' => $validity->shiftIds()->required()->toArray(),
            'shift_ids.*' => $validity->shiftId()->required()->toArray(),
        ]);
        $store = ActiveStoreResolver::resolve($request, $admin);
        if (!$store instanceof Store) {
            \abort(404);
        }

        $count = (new WorkforceManagementService())->deleteShifts(
            $admin,
            $store,
            $validated->parseInt('year'),
            $validated->parseInt('month'),
            \array_values(\array_map(static fn(mixed $id): int => Typer::parseInt($id), $validated->assertArray('shift_ids'))),
        );
        Inertia::flash('success', \__('Selected shifts deleted: :count.', ['count' => $count]));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'store_id' => $store->getKey(),
            'year' => $validated->parseInt('year'),
            'month' => $validated->parseInt('month'),
        ]);
    }
}
