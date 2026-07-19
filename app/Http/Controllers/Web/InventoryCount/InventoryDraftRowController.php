<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\InventorySession;
use App\Models\User;
use App\Services\InventorySessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryDraftRowController
{
    /**
     * Autosave one counted inventory row.
     */
    public function __invoke(Request $request, InventorySession $session, InventorySessionService $service): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0'],
            'classification' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:2000'],
            'client_version' => ['required', 'integer', 'min:1'],
        ]);
        $row = $service->saveDraftRow(User::mustAuth(), $session, Typer::assertArray($validated));

        return new JsonResponse([
            'saved' => true,
            'client_version' => Typer::parseInt($row->getAttribute('client_version')),
            'counted_at' => $row->getCountedAt()?->toJSON(),
            'expected_quantity' => $row->getExpectedQuantity(),
            'difference' => $row->getQuantityDifference(),
        ]);
    }
}
