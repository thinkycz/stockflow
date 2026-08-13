<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftShareLinkValidity;
use App\Models\ShiftShareLink;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class ShiftShareLinkStoreController
{
    use ValidatesWebRequests;

    /**
     * Create a named public link for the active store.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();

        if (!$admin->isAdmin()) {
            \abort(403);
        }

        $store = ActiveStoreResolver::resolve($request, $admin);

        if (!$store instanceof Store) {
            \abort(404);
        }

        $name = $request->input('name');
        if (\is_string($name)) {
            $request->merge(['name' => \mb_trim($name)]);
        }

        $validated = $this->validateRequest($request, [
            'name' => ShiftShareLinkValidity::inject($store->getKey())->name()->required()->toArray(),
        ]);

        ShiftShareLink::query()->create([
            'user_id' => $admin->getKey(),
            'store_id' => $store->getKey(),
            'name' => $validated->assertString('name'),
            'token' => Str::random(64),
        ]);

        Inertia::flash('success', \__('Public link created.'));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'store_id' => $store->getKey(),
            'month' => $request->query('month'),
            'year' => $request->query('year'),
        ]);
    }
}
