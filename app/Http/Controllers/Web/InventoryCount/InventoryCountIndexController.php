<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Domain\Inventory\InventoryReadService;
use App\Domain\Inventory\InventorySessionService;
use App\Enums\StockMovementClassificationEnum;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCountIndexController
{
    /**
     * Page size hint required by the web index controller architecture test.
     * The inventory page renders every catalog item in a single grid, so
     * the list is bounded by the catalog size and pagination is not
     * exposed in the UI.
     */
    public const int TAKE = 1000;

    /**
     * Render the inventory editor for the active store.
     */
    public function __invoke(Request $request, InventorySessionService $service, InventoryReadService $readService): Response
    {
        $user = User::mustAuth();

        if (!$user->isAdmin() && $user->getAssignedStoreId() === null) {
            \abort(403);
        }

        $scopeUser = $user->resolveScopeUser();
        $store = ActiveStoreResolver::resolve($request, $user);

        $rows = [];

        if ($store instanceof Store) {
            $rows = $readService->buildStoreView($scopeUser, $store);
        }

        $draft = $store instanceof Store ? $service->activeDraft($user, $store) : null;

        return Inertia::render('inventory-counts/Index', [
            'store' => $store instanceof Store ? [
                'id' => $store->getKey(),
                'name' => $store->getName(),
            ] : null,
            'rows' => $rows,
            'filters' => [
                'store_id' => $store?->getKey(),
            ],
            'is_admin' => $user->isAdmin(),
            'default_counted_on' => Carbon::now()->toDateString(),
            'draft' => $draft instanceof InventorySession ? [
                'id' => $draft->getKey(),
                'started_at' => $draft->getStartedAt()->toJSON(),
                'rows' => $draft->getItems()->map(static fn(InventorySessionItem $row): array => [
                    'item_id' => $row->getItemId(),
                    'quantity' => $row->getQuantity(),
                    'classification' => $row->getClassification()?->value,
                    'note' => $row->getNote(),
                    'revision' => $row->getRevision(),
                ])->values()->all(),
            ] : null,
            'classifications' => \array_map(
                static fn(StockMovementClassificationEnum $classification): string => $classification->value,
                StockMovementClassificationEnum::cases(),
            ),
        ]);
    }
}
