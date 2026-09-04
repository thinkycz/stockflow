<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Http\Validation\InventoryCountValidity;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ManageInventory
{
    /**
     * Create the shared inventory lifecycle command.
     */
    public function __construct(
        private readonly InventorySessionService $sessions,
        private readonly StockMovementService $movements,
    ) {}

    /**
     * Start or resume the active inventory draft for an owned store.
     */
    public function startDraft(User $actor, int $storeId): InventorySession
    {
        return $this->sessions->startDraft($actor, $this->store($actor, $storeId));
    }

    /**
     * Save a typed inventory draft row through the shared lifecycle service.
     */
    public function saveDraftRow(User $actor, int $sessionId, InventoryDraftRowInput $input): InventorySessionItem
    {
        return $this->sessions->saveDraftRow($actor, $this->draft($actor, $sessionId), $input);
    }

    /**
     * Close a draft and post its normal reconciliation movement.
     *
     * @param array<string, mixed> $payload
     */
    public function closeDraft(User $actor, int $sessionId, array $payload): InventorySession
    {
        $validated = Resolver::resolveValidatorFactory()->make($payload, [
            'counted_on' => InventoryCountValidity::inject($actor->resolveScopeUser()->getKey())->countedOn()->required()->toArray(),
        ])->validate();

        return $this->sessions->closeDraft(
            $actor,
            $this->draft($actor, $sessionId),
            Carbon::createFromFormat('Y-m-d', Typer::assertString($validated['counted_on'] ?? null)),
        );
    }

    /**
     * Cancel an owned open draft without posting stock.
     */
    public function cancelDraft(User $actor, int $sessionId): void
    {
        $this->sessions->cancelDraft($actor, $this->draft($actor, $sessionId));
    }

    /**
     * Create and close a complete inventory count session.
     *
     * @param array<string, mixed> $payload
     */
    public function createCount(User $actor, array $payload): InventorySession
    {
        $owner = $actor->resolveScopeUser();
        $validity = InventoryCountValidity::inject($owner->getKey());
        $validated = Resolver::resolveValidatorFactory()->make($payload, [
            'store_id' => $validity->storeId()->required()->toArray(),
            'rows' => $validity->rows()->required()->toArray(),
            'rows.*.item_id' => $validity->itemId()->required()->toArray(),
            'rows.*.quantity' => $validity->rowQuantity()->nullable()->toArray(),
            'rows.*.classification' => $validity->rowClassification()->nullable()->toArray(),
            'rows.*.note' => $validity->rowNote()->nullable()->toArray(),
        ])->validate();
        $store = $this->store($actor, Typer::assertInt($validated['store_id'] ?? null));

        if (!$actor->isAdmin() && $actor->getAssignedStoreId() !== $store->getKey()) {
            \abort(403);
        }

        return $this->sessions->createSession(
            $actor,
            $store,
            $this->rows($validated['rows'] ?? null),
        );
    }

    /**
     * Create an immutable reversal through the normal movement service.
     *
     * @param array<string, mixed> $payload
     */
    public function reverseMovement(User $actor, int $movementId, array $payload): StockMovement
    {
        $validated = Resolver::resolveValidatorFactory()->make($payload, [
            'reason' => ['required', 'string', 'max:2000'],
        ])->validate();
        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $actor->resolveScopeUser());
        $movement = Typer::assertInstance($query->whereKey($movementId)->firstOrFail(), StockMovement::class);

        return $this->movements->reverseMovement(
            $movement,
            $actor,
            Typer::assertString($validated['reason'] ?? null),
        );
    }

    /**
     * Resolve an owned store and reject cross-company identifiers.
     */
    public function store(User $actor, int $storeId): Store
    {
        $query = Store::query();
        Store::scopeForUser($query, $actor->resolveScopeUser());

        return Typer::assertInstance($query->whereKey($storeId)->firstOrFail(), Store::class);
    }

    /**
     * Resolve an owned inventory draft.
     */
    public function draft(User $actor, int $sessionId): InventorySession
    {
        return Typer::assertInstance(
            InventorySession::query()
                ->where('user_id', $actor->resolveScopeUser()->getKey())
                ->whereKey($sessionId)
                ->firstOrFail(),
            InventorySession::class,
        );
    }

    /**
     * Normalize validated inventory rows into the service contract.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rows(mixed $value): array
    {
        $rows = [];

        foreach (Typer::assertArray($value) as $row) {
            $rows[] = Typer::assertStringKeyArray(Typer::assertArray($row));
        }

        return $rows;
    }
}
