<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\InventorySession;
use App\Models\User;
use App\Services\InventorySessionService;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCountShowController
{
    /**
     * Render the read-only detail of a single inventory session.
     *
     * The session is resolved through `BelongsToUser` so foreign
     * sessions are rejected automatically. Limited users are pinned to
     * their assigned store to prevent store hopping via crafted URLs.
     */
    public function __invoke(InventorySession $session, InventorySessionService $service): Response
    {
        $user = User::mustAuth();

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
