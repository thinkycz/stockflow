<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ShiftPreset;

use App\Domain\Workforce\WorkforceManagementService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftPresetValidity;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class ShiftPresetStoreController
{
    use ValidatesWebRequests;

    /**
     * Create a preset for the active store.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);

        if (!$store instanceof Store) {
            \abort(404);
        }

        $name = $request->input('name');

        if (\is_string($name)) {
            $request->merge(['name' => \mb_trim($name)]);
        }

        $validity = ShiftPresetValidity::inject($store->getKey());
        $validated = $this->validateRequest($request, [
            'name' => $validity->name()->required()->toArray(),
            'start_time' => $validity->startTime()->required()->toArray(),
            'end_time' => $validity->endTime()->required()->toArray(),
        ]);

        (new WorkforceManagementService())->createPreset(
            $admin,
            $store,
            $validated->assertString('name'),
            $validated->assertString('start_time'),
            $validated->assertString('end_time'),
        );

        Inertia::flash('success', \__('Shift preset created.'));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'month' => $request->query('month'),
            'year' => $request->query('year'),
        ]);
    }
}
