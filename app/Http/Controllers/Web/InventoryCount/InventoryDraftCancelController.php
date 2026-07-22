<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\InventorySession;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryDraftCancelController
{
    /**
     * Cancel an open draft without changing stock.
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

        $service->cancelDraft($user, $session);

        return Resolver::resolveRedirector()->route('inventory-counts.index');
    }
}
