<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\InventorySession;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Http\RedirectResponse;
use Thinkycz\LaravelCore\Support\Resolver;

class InventoryDraftCancelController
{
    /**
     * Cancel an open draft without changing stock.
     */
    public function __invoke(InventorySession $session, InventorySessionService $service): RedirectResponse
    {
        $service->cancelDraft(User::mustAuth(), $session);

        return Resolver::resolveRedirector()->route('inventory-counts.index');
    }
}
