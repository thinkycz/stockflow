<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Models\User;
use App\Operations\Inventory\ManageInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Typer;

class InventoryDraftRowController
{
    /**
     * Autosave one counted inventory row.
     */
    public function __invoke(Request $request, ManageInventory $operation): JsonResponse
    {
        $row = $operation->saveDraftRow(
            User::mustAuth(),
            Typer::parseInt($request->route('session')),
            Typer::assertStringKeyArray($request->all()),
        );

        return new JsonResponse([
            'saved' => true,
            'client_version' => Typer::parseInt($row->getAttribute('client_version')),
            'counted_at' => $row->getCountedAt()?->toJSON(),
            'expected_quantity' => $row->getExpectedQuantity(),
            'difference' => $row->getQuantityDifference(),
        ]);
    }
}
