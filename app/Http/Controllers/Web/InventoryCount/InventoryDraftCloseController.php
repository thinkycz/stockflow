<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\InventorySession;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryDraftCloseController
{
    /**
     * Atomically close an inventory draft and post its reconciliation.
     */
    public function __invoke(Request $request, InventorySessionService $service): RedirectResponse
    {
        $user = User::mustAuth();
        $session = InventorySession::query()
            ->where('user_id', $user->resolveScopeUser()->getKey())
            ->whereKey(Typer::parseInt($request->route('session')))
            ->first();

        if (!$session instanceof InventorySession) {
            \abort(404);
        }

        $service->closeDraft($user, $session);
        Inertia::flash('success', \__('Inventory count saved.'));

        return Resolver::resolveRedirector()->route('inventory-counts.show', ['session' => $session->getKey()]);
    }
}
