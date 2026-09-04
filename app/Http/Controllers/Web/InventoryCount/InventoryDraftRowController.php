<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\InventoryCount;

use App\Domain\Inventory\InventoryDraftRowInput;
use App\Domain\Inventory\ManageInventory;
use App\Exceptions\InventoryRevisionConflictException;
use App\Models\User;
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
        try {
            $row = $operation->saveDraftRow(
                User::mustAuth(),
                Typer::parseInt($request->route('session')),
                InventoryDraftRowInput::fromPayload(Typer::assertStringKeyArray($request->all())),
            );
        } catch (InventoryRevisionConflictException $exception) {
            return new JsonResponse([
                'saved' => false,
                'row' => $exception->row?->draftValues(),
                'revision' => $exception->row?->getRevision() ?? 0,
            ], 409);
        }

        return new JsonResponse(['saved' => true, 'row' => $row->draftValues(), 'revision' => $row->getRevision()]);
    }
}
