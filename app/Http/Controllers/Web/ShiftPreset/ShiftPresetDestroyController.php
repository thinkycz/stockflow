<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ShiftPreset;

use App\Domain\Workforce\WorkforceManagementService;
use App\Models\ShiftPreset;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class ShiftPresetDestroyController
{
    /**
     * Delete a preset from the active store.
     */
    public function __invoke(Request $request, ShiftPreset $shiftPreset): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);

        if (!$store instanceof Store || $shiftPreset->getStoreId() !== $store->getKey()) {
            \abort(404);
        }

        (new WorkforceManagementService())->deletePreset($admin, $store, $shiftPreset);
        Inertia::flash('success', \__('Shift preset deleted.'));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'month' => $request->query('month'),
            'year' => $request->query('year'),
        ]);
    }
}
