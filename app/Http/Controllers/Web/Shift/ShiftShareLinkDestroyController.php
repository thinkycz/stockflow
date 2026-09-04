<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Domain\Workforce\WorkforceManagementService;
use App\Models\ShiftShareLink;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class ShiftShareLinkDestroyController
{
    /**
     * Revoke a public link belonging to the active store.
     */
    public function __invoke(Request $request, int $shiftShareLink): RedirectResponse
    {
        $admin = User::mustAuth();

        if (!$admin->isAdmin()) {
            \abort(403);
        }

        $store = ActiveStoreResolver::resolve($request, $admin);
        $linkQuery = ShiftShareLink::query();
        ShiftShareLink::scopeForUser($linkQuery, $admin);
        $link = $linkQuery->whereKey($shiftShareLink)->first();

        if (
            !$store instanceof Store ||
            !$link instanceof ShiftShareLink ||
            $link->getStoreId() !== $store->getKey()
        ) {
            \abort(404);
        }

        (new WorkforceManagementService())->deleteShareLink($admin, $store, $link);
        Inertia::flash('success', \__('Public link deleted.'));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'store_id' => $store->getKey(),
            'month' => $request->query('month'),
            'year' => $request->query('year'),
        ]);
    }
}
