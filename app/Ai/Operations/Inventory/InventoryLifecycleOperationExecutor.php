<?php

declare(strict_types=1);

namespace App\Ai\Operations\Inventory;

use App\Ai\Operations\AssistantOperationExecutor;
use App\Models\InventorySession;
use App\Models\InventorySessionItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Operations\Inventory\ManageInventory;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class InventoryLifecycleOperationExecutor implements AssistantOperationExecutor
{
    /**
     * Create the assistant adapter around the shared inventory command.
     */
    public function __construct(
        private readonly ManageInventory $command,
    ) {}

    /**
     * Validate ownership and return the exact target/effects preview.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function preview(string $identifier, User $actor, array $arguments): array
    {
        $storeId = $this->nullableId($arguments, 'store_id');
        $targetId = $this->nullableId($arguments, 'target_id');
        $values = $this->values($arguments);
        $store = $storeId === null ? null : $this->command->store($actor, $storeId);
        $target = match ($identifier) {
            'reverse_stock_movement' => $this->ownedMovement($actor, Typer::assertInt($targetId)),
            'save_inventory_draft_row', 'close_inventory_draft', 'cancel_inventory_draft' => $this->command->draft($actor, Typer::assertInt($targetId)),
            'start_inventory_draft', 'create_inventory_count' => null,
            default => throw new InvalidArgumentException('Unknown inventory lifecycle operation.'),
        };

        $this->validateValues($identifier, $actor, $storeId, $targetId, $values);

        return [
            'operation' => $identifier,
            'store' => $store === null ? null : ['id' => $store->getKey(), 'name' => $store->getName()],
            'target' => $target === null ? null : [
                'type' => $target instanceof StockMovement ? 'stock_movement' : 'inventory_session',
                'id' => (string) $target->getKey(),
            ],
            'effects' => $this->effects($identifier),
            'sanitized_arguments' => ['values' => $values],
            'safe_editable_fields' => ['values_json'],
        ];
    }

    /**
     * Execute an approved inventory operation through ManageInventory.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    public function execute(string $identifier, User $actor, array $arguments): array
    {
        $storeId = $this->nullableId($arguments, 'store_id');
        $targetId = $this->nullableId($arguments, 'target_id');
        $values = $this->values($arguments);
        $result = match ($identifier) {
            'reverse_stock_movement' => $this->command->reverseMovement($actor, Typer::assertInt($targetId), $values),
            'start_inventory_draft' => $this->command->startDraft($actor, Typer::assertInt($storeId)),
            'save_inventory_draft_row' => $this->command->saveDraftRow($actor, Typer::assertInt($targetId), $values),
            'close_inventory_draft' => $this->command->closeDraft($actor, Typer::assertInt($targetId), $values),
            'cancel_inventory_draft' => $this->cancelDraft($actor, Typer::assertInt($targetId)),
            'create_inventory_count' => $this->command->createCount($actor, ['store_id' => $storeId, ...$values]),
            default => throw new InvalidArgumentException('Unknown inventory lifecycle operation.'),
        };

        return [
            'operation' => $identifier,
            'status' => 'succeeded',
            'record' => $this->record($result),
        ];
    }

    /**
     * Decode the strictly bounded editable JSON values.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function values(array $arguments): array
    {
        $json = Typer::assertString($arguments['values_json'] ?? null);
        $decoded = \json_decode($json, true, 32, \JSON_THROW_ON_ERROR);

        return Typer::assertStringKeyArray(Typer::assertArray($decoded));
    }

    /**
     * Parse one locked nullable identifier.
     *
     * @param array<string, mixed> $arguments
     */
    private function nullableId(array $arguments, string $key): int|null
    {
        return Typer::parseNullableInt($arguments[$key] ?? null);
    }

    /**
     * Run the same validation paths without retaining a domain mutation.
     *
     * @param array<string, mixed> $values
     */
    private function validateValues(string $identifier, User $actor, int|null $storeId, int|null $targetId, array $values): void
    {
        match ($identifier) {
            'reverse_stock_movement' => Resolver::resolveValidatorFactory()->make($values, ['reason' => ['required', 'string', 'max:2000']])->validate(),
            'start_inventory_draft' => $this->command->store($actor, Typer::assertInt($storeId)),
            'save_inventory_draft_row' => Resolver::resolveValidatorFactory()->make($values, [
                'item_id' => ['required', 'integer'],
                'quantity' => ['required', 'numeric', 'min:0'],
                'classification' => ['nullable', 'string'],
                'note' => ['nullable', 'string'],
                'client_version' => ['required', 'integer', 'min:1'],
            ])->validate(),
            'close_inventory_draft' => Resolver::resolveValidatorFactory()->make($values, ['counted_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']])->validate(),
            'cancel_inventory_draft' => $this->command->draft($actor, Typer::assertInt($targetId)),
            'create_inventory_count' => Resolver::resolveValidatorFactory()->make($values, ['rows' => ['required', 'array', 'min:1']])->validate(),
            default => throw new InvalidArgumentException('Unknown inventory lifecycle operation.'),
        };
    }

    /**
     * Cancel a draft and return the resolved record for a safe result.
     */
    private function cancelDraft(User $actor, int $targetId): InventorySession
    {
        $draft = $this->command->draft($actor, $targetId);
        $this->command->cancelDraft($actor, $targetId);

        return $draft->refresh();
    }

    /**
     * Resolve one owned stock movement for a preview.
     */
    private function ownedMovement(User $actor, int $targetId): StockMovement
    {
        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $actor->resolveScopeUser());

        return Typer::assertInstance($query->whereKey($targetId)->firstOrFail(), StockMovement::class);
    }

    /**
     * Describe the normal application effects shown before approval.
     *
     * @return list<string>
     */
    private function effects(string $identifier): array
    {
        return match ($identifier) {
            'reverse_stock_movement' => ['Creates one immutable compensating movement.', 'Restores inventory through the normal transaction and activity pipeline.'],
            'start_inventory_draft' => ['Creates or resumes the store inventory draft with its opening snapshot.'],
            'save_inventory_draft_row' => ['Updates one draft row with optimistic concurrency.'],
            'close_inventory_draft' => ['Closes the draft and posts its reconciliation movement and activity.'],
            'cancel_inventory_draft' => ['Cancels the draft without changing inventory balances.'],
            'create_inventory_count' => ['Creates a completed count session and its normal reconciliation records.'],
            default => throw new InvalidArgumentException('Unknown inventory lifecycle operation.'),
        };
    }

    /**
     * Build a bounded record result.
     *
     * @return array<string, mixed>
     */
    private function record(InventorySession|InventorySessionItem|StockMovement $model): array
    {
        return [
            'type' => match (true) {
                $model instanceof StockMovement => 'stock_movement',
                $model instanceof InventorySessionItem => 'inventory_session_item',
                default => 'inventory_session',
            },
            'id' => $model->getKey(),
            'url' => $model instanceof InventorySession
                ? Resolver::resolveUrlGenerator()->route('inventory-counts.show', $model->getKey())
                : ($model instanceof StockMovement
                    ? Resolver::resolveUrlGenerator()->route('stock-movements.show', $model->getKey())
                    : null),
        ];
    }
}
