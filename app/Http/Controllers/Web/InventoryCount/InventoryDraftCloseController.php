<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\InventorySession;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class InventoryDraftCloseController
{
    /**
     * Atomically close an inventory draft and post its reconciliation.
     */
    public function __invoke(InventorySession $session, InventorySessionService $service): RedirectResponse
    {
        $service->closeDraft(User::mustAuth(), $session);
        Inertia::flash('success', \__('Inventory count saved.'));

        return Resolver::resolveRedirector()->route('inventory-counts.show', ['session' => $session->getKey()]);
    }
}
