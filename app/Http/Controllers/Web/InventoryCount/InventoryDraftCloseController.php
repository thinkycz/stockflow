<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\InventoryCountValidity;
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
    use ValidatesWebRequests;

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

        $validated = $this->validateRequest($request, [
            'counted_on' => InventoryCountValidity::inject($user->resolveScopeUser()->getKey())->countedOn()->required()->toArray(),
        ]);

        $service->closeDraft($user, $session, $validated->mustParseCarbon('counted_on', 'Y-m-d'));
        Inertia::flash('success', \__('Inventory count saved.'));

        return Resolver::resolveRedirector()->route('inventory-counts.show', ['session' => $session->getKey()]);
    }
}
