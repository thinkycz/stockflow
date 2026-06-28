<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\InventorySession;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryCountShowController
{
    /**
     * Render the read-only detail of a single inventory session.
     *
     * The session is resolved through the scope user (admin or parent)
     * so limited users can view sessions owned by their admin. A limited
     * user is pinned to their assigned store to prevent store hopping.
     */
    public function __invoke(Request $request, InventorySessionService $service): Response
    {
        $user = User::mustAuth();
        $scopeUser = $user->resolveScopeUser();

        $session = InventorySession::query()
            ->where('user_id', $scopeUser->getKey())
            ->whereKey(Typer::parseInt($request->route('session')))
            ->first();

        if (!$session instanceof InventorySession) {
            \abort(404);
        }

        if (!$user->isAdmin()) {
            $assignedStoreId = $user->getAssignedStoreId();

            if ($assignedStoreId === null) {
                \abort(403);
            }

            $store = $session->store()->first();

            if ($store === null || $assignedStoreId !== $store->getKey()) {
                \abort(403);
            }
        }

        $rows = $service->buildSessionView($user, $session);
        $store = $session->getStore();
        $creator = $session->creator()->first();

        return Inertia::render('inventory-counts/Show', [
            'session' => [
                'id' => $session->getKey(),
                'store_id' => $store->getKey(),
                'store_name' => $store->getName(),
                'counted_at' => $session->getCountedAt()->toJSON(),
                'note' => $session->getNote(),
                'created_by' => $session->getCreatedBy(),
                'created_by_email' => $creator?->getEmail(),
            ],
            'rows' => $rows,
        ]);
    }
}
