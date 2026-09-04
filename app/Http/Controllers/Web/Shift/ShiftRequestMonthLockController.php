<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Domain\Workforce\ShiftRequestService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftRequestValidity;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class ShiftRequestMonthLockController
{
    use ValidatesWebRequests;

    /**
     * Lock or reopen public requests for one future month.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);
        if (!$store instanceof Store) {
            \abort(404);
        }

        $validity = ShiftRequestValidity::inject($admin->getKey());
        $validated = $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'locked' => $validity->locked()->required()->toArray(),
        ]);
        $locked = $validated->parseBool('locked');
        (new ShiftRequestService())->setLocked(
            $admin,
            $store,
            $validated->parseInt('year'),
            $validated->parseInt('month'),
            $locked,
        );
        Inertia::flash('success', $locked ? \__('Shift requests locked.') : \__('Shift requests reopened.'));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'year' => $validated->parseInt('year'),
            'month' => $validated->parseInt('month'),
        ]);
    }
}
