<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\AssistantActionPresenter;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Operations\Inventory\CreateStockMovement;
use App\Operations\Inventory\ManageInventory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class WriteStockMovementsTool extends AbstractApprovableResourceTool
{
    /**
     * Return the stable provider-facing tool name.
     */
    public function name(): string
    {
        return 'write_stock_movements';
    }

    /**
     * Describe stock movement and reversal mutations to the model.
     */
    public function description(): string
    {
        return 'Create incoming, transfer, consumption, or adjustment movements, or reverse an existing movement. Item and store identities are locked; business quantities and notes may be edited during approval.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $quantityItems = fn(): ObjectType => $schema->object([
            'item_id' => $schema->integer()->required(),
            'quantity' => $schema->number()->min(0)->required(),
        ])->withoutAdditionalProperties();
        $adjustmentItems = fn(): ObjectType => $schema->object([
            'item_id' => $schema->integer()->required(),
            'quantity_after' => $schema->number()->min(0)->required(),
            'adjustment_reason' => $schema->string()->required(),
        ])->withoutAdditionalProperties();
        $values = fn(ObjectType $item): ObjectType => $schema->object([
            'note' => $schema->string(),
            'occurred_at' => $schema->string()->description('Optional ISO date-time not later than now.'),
            'items' => $schema->array()->items($item)->min(1)->max(50)->required(),
        ])->withoutAdditionalProperties();

        return ['request' => $schema->anyOf([
            $schema->object([
                'action' => $schema->string()->enum(['create_stock_movement'])->required(),
                'mode' => $schema->string()->enum(['incoming'])->required(),
                'store_id' => $schema->integer()->required(),
                'values' => $values($quantityItems())->required(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'action' => $schema->string()->enum(['create_stock_movement'])->required(),
                'mode' => $schema->string()->enum(['consumption'])->required(),
                'store_id' => $schema->integer()->required(),
                'values' => $values($quantityItems())->required(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'action' => $schema->string()->enum(['create_stock_movement'])->required(),
                'mode' => $schema->string()->enum(['transfer'])->required(),
                'store_id' => $schema->integer()->required(),
                'source_store_id' => $schema->integer()->required(),
                'values' => $values($quantityItems())->required(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'action' => $schema->string()->enum(['create_stock_movement'])->required(),
                'mode' => $schema->string()->enum(['adjustment'])->required(),
                'store_id' => $schema->integer()->required(),
                'values' => $values($adjustmentItems())->required(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'action' => $schema->string()->enum(['reverse_stock_movement'])->required(),
                'target_id' => $schema->integer()->required(),
                'values' => $schema->object([
                    'reason' => $schema->string()->required(),
                ])->withoutAdditionalProperties()->required(),
            ])->withoutAdditionalProperties(),
        ])->required()];
    }

    /**
     * Return the inventory audit domain.
     */
    public function auditDomain(): string
    {
        return 'stock_movements';
    }

    /**
     * @param array<string, mixed> $arguments @return list<string>
     */
    public function safeEditablePaths(array $arguments): array
    {
        if ($this->action($arguments) === 'reverse_stock_movement') {
            return ['request.values.reason'];
        }

        $paths = ['request.values.note', 'request.values.occurred_at'];
        $mode = Typer::assertString($this->request($arguments)['mode'] ?? null);

        return $mode === 'adjustment'
            ? [...$paths, 'request.values.items.*.quantity_after', 'request.values.items.*.adjustment_reason']
            : [...$paths, 'request.values.items.*.quantity'];
    }

    /**
     * @param array<string, mixed> $arguments @return array{store_id: int|null, store_name: string|null, target_type: string|null, target_id: string|null}
     */
    public function auditContext(array $arguments): array
    {
        $request = $this->request($arguments);
        $movement = $this->action($arguments) === 'reverse_stock_movement'
            ? $this->movement(Typer::assertInt($request['target_id'] ?? null))
            : null;
        $storeId = $movement?->getStoreId() ?? Typer::parseNullableInt($request['store_id'] ?? null);
        $store = $storeId === null ? null : $this->store($storeId);

        return [
            'store_id' => $store?->getKey(),
            'store_name' => $store?->getName(),
            'target_type' => $movement instanceof StockMovement ? 'stock_movement' : null,
            'target_id' => $movement instanceof StockMovement ? (string) $movement->getKey() : null,
        ];
    }

    /**
     * @param array<string, mixed> $arguments @return array<string, mixed>
     */
    protected function preview(array $arguments): array
    {
        $request = $this->request($arguments);
        $action = $this->action($arguments);
        $values = $this->values($arguments);

        if ($action === 'create_stock_movement') {
            $validated = Resolver::resolve(CreateStockMovement::class)->validate($this->actor, $this->payload($request, $values));
            $store = $this->store(Typer::assertInt($validated['store_id'] ?? null));
            $target = null;
            $effects = ['Creates one immutable movement and its item rows.', 'Updates inventory balances through the normal transaction.', 'Creates the normal operational activity and notifications.'];
        } else {
            $movement = $this->movement(Typer::assertInt($request['target_id'] ?? null));
            Resolver::resolveValidatorFactory()->make($values, ['reason' => ['required', 'string', 'max:2000']])->validate();
            $store = $this->store(Typer::assertInt($movement->getStoreId()));
            $target = ['type' => 'stock_movement', 'id' => (string) $movement->getKey()];
            $effects = ['Creates an immutable compensating movement through the normal reversal service.'];
        }

        return Resolver::resolve(AssistantActionPresenter::class)->present($arguments, [
            'store' => ['id' => $store->getKey(), 'name' => $store->getName()],
            'target' => $target,
            'effects' => $effects,
            'business_rows' => $action === 'create_stock_movement'
                ? $this->businessRows(Typer::assertArray($values['items'] ?? []))
                : [],
        ]);
    }

    /**
     * @param array<string, mixed> $arguments @return array<string, mixed>
     */
    protected function execute(array $arguments): array
    {
        $request = $this->request($arguments);
        $action = $this->action($arguments);

        if ($action === 'create_stock_movement') {
            $movement = Resolver::resolve(CreateStockMovement::class)->execute($this->actor, $this->payload($request, $this->values($arguments)));
        } elseif ($action === 'reverse_stock_movement') {
            $movement = Resolver::resolve(ManageInventory::class)->reverseMovement(
                $this->actor,
                Typer::assertInt($request['target_id'] ?? null),
                $this->values($arguments),
            );
        } else {
            throw new InvalidArgumentException('Unknown stock movement action.');
        }

        return [
            'operation' => $action,
            'status' => 'succeeded',
            'record' => [
                'type' => 'stock_movement',
                'id' => $movement->getKey(),
                'number' => $movement->getNumber(),
                'store_id' => $movement->getStoreId(),
                'url' => Resolver::resolveUrlGenerator()->route('stock-movements.show', $movement->getKey()),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function payload(array $request, array $values): array
    {
        return [
            'mode' => $request['mode'] ?? null,
            'store_id' => $request['store_id'] ?? null,
            'source_store_id' => $request['source_store_id'] ?? null,
            'note' => $values['note'] ?? null,
            'occurred_at' => $values['occurred_at'] ?? null,
            'items' => $values['items'] ?? null,
        ];
    }

    /**
     * Resolve validated item identities into bounded human-readable rows.
     *
     * @param array<array-key, mixed> $rows
     *
     * @return list<array{label: string, value: string|null}>
     */
    private function businessRows(array $rows): array
    {
        $itemIds = [];

        foreach ($rows as $row) {
            if (\is_array($row) && \is_int($row['item_id'] ?? null)) {
                $itemIds[] = $row['item_id'];
            }
        }

        $items = Item::query()
            ->where('user_id', $this->actor->resolveScopeUser()->getKey())
            ->whereKey($itemIds)
            ->get()
            ->keyBy(static fn(Item $item): int => $item->getKey());
        $result = [];

        foreach (\array_slice($rows, 0, 50) as $row) {
            if (!\is_array($row) || !\is_int($row['item_id'] ?? null)) {
                continue;
            }

            $item = $items->get($row['item_id']);

            if (!$item instanceof Item) {
                continue;
            }

            $quantity = $row['quantity_after'] ?? $row['quantity'] ?? null;
            $result[] = [
                'label' => $item->getTitle(),
                'value' => \is_scalar($quantity)
                    ? (string) $quantity . ($item->getUnit() === null ? '' : ' ' . $item->getUnit())
                    : null,
            ];
        }

        return $result;
    }

    /**
     * Resolve one owned store or fail without exposing a foreign record.
     */
    private function store(int $id): Store
    {
        return Typer::assertInstance(Store::query()->where('user_id', $this->actor->resolveScopeUser()->getKey())->whereKey($id)->firstOrFail(), Store::class);
    }

    /**
     * Resolve one owned stock movement or fail without exposing a foreign record.
     */
    private function movement(int $id): StockMovement
    {
        $query = StockMovement::query();
        StockMovement::scopeForUser($query, $this->actor->resolveScopeUser());

        return Typer::assertInstance($query->whereKey($id)->firstOrFail(), StockMovement::class);
    }
}
