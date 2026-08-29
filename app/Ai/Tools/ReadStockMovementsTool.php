<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\StockMovement;
use App\Models\StockMovementItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadStockMovementsTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_stock_movements'; }

    /**
     * Explain the stock-movement facts available to the model.
     */
    public function description(): string { return 'Read stock receipts, transfers, inventory changes, consumption, reversals, source and destination stores, line items, quantities, values, classifications, and reversal chains.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array { $filters = ['search' => $schema->string(), 'store_id' => $schema->integer(), 'source_store_id' => $schema->integer(), 'destination_store_id' => $schema->integer(), 'type' => $schema->string(), 'date_from' => $schema->string(), 'date_to' => $schema->string(), 'reversed' => $schema->boolean()];

        return ['request' => $schema->anyOf([$schema->object(['operation' => $schema->string()->enum(['list'])->required(), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), ...$filters])->withoutAdditionalProperties()])->required()]; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array { $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        $query = StockMovement::query()->with(['store', 'sourceStore', 'movementItems.item']);
        StockMovement::scopeForUser($query, $this->actor->resolveScopeUser());
        $this->filters($query, $request);
        if ($operation === 'detail') { $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('A stock movement identifier is required.'); }

            return $this->detailResult($request, 'movements', $this->record($query->findOrFail($id), true)); } if ($operation === 'summary') { $movements = $query->get();

                return $this->summaryResult($request, 'movements', ['movement_count' => $movements->count(), 'total_quantity' => $movements->sum(static fn(StockMovement $movement): int => $movement->getTotalQuantity()), 'gross_value' => \round($movements->sum(static fn(StockMovement $movement): float => $movement->getTotalValue()), 2), 'net_value' => \round($movements->sum(static fn(StockMovement $movement): float => $movement->getNetValue()), 2), 'reversed_count' => $movements->filter(static fn(StockMovement $movement): bool => $movement->getReversedAt() !== null)->count(), 'by_type' => $movements->countBy(static fn(StockMovement $movement): string => $movement->getType()->value)->all()], $movements->isEmpty() ? 'NO_MATCHING_DATA' : null); } if ($operation !== 'list') { throw new InvalidArgumentException('Unknown stock movement read operation.'); }

        return $this->paginateById($query, $request, 'movements', $request, fn(StockMovement $movement): array => $this->record($movement, false)); }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'stock_movements'; }

    /**
     * @param Builder<StockMovement> $query
     * @param array<string, mixed> $request
     */
    private function filters(Builder $query, array $request): void { $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') { StockMovement::scopeSearch($query, \mb_trim($search)); } $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) { $this->ownedStore($storeId);
            $query->where(static fn(Builder $scope): Builder => $scope->where('store_id', $storeId)->orWhere('source_store_id', $storeId)); } foreach (['source_store_id' => 'source_store_id', 'destination_store_id' => 'store_id'] as $filter => $column) { $id = Typer::parseNullableInt($request[$filter] ?? null);
                if ($id !== null) { $this->ownedStore($id);
                    $query->where($column, $id); } } $type = Typer::parseNullableString($request['type'] ?? null);
        if ($type !== null) { $query->where('type', $type); } $from = Typer::parseNullableString($request['date_from'] ?? null);
        if ($from !== null) { $query->where('occurred_at', '>=', $from . ' 00:00:00'); } $to = Typer::parseNullableString($request['date_to'] ?? null);
        if ($to !== null) { $query->where('occurred_at', '<=', $to . ' 23:59:59'); } if (\array_key_exists('reversed', $request)) { (bool) $request['reversed'] ? $query->whereNotNull('reversed_at') : $query->whereNull('reversed_at'); } }

    /**
     * @return array<string, mixed>
     */
    private function record(StockMovement $movement, bool $includeItems): array { $record = ['id' => $movement->getKey(), 'number' => $movement->getNumber(), 'type' => $movement->getType()->value, 'origin' => $movement->getOrigin()->value, 'store_id' => $movement->getStoreId(), 'store_name' => $movement->getStore()?->getName(), 'source_store_id' => $movement->getSourceStoreId(), 'source_store_name' => $movement->getSourceStore()?->getName(), 'occurred_at' => $movement->getOccurredAt()->toJSON(), 'items_count' => $movement->getItemsCount(), 'total_quantity' => $movement->getTotalQuantity(), 'total_value' => $movement->getTotalValue(), 'net_value' => $movement->getNetValue(), 'note' => $movement->getNote(), 'reversal_of_id' => $movement->getReversalOfId(), 'reversal_reason' => $movement->getReversalReason(), 'reversed_at' => $movement->getReversedAt()?->toJSON(), 'url' => Resolver::resolveUrlGenerator()->route('stock-movements.show', $movement->getKey())];
        if ($includeItems) { $record['items'] = $movement->getMovementItems()->map(static fn(StockMovementItem $item): array => ['item_id' => $item->getItemId(), 'item_title' => $item->getItem()->getTitle(), 'quantity' => $item->getQuantity(), 'quantity_before' => $item->getQuantityBefore(), 'quantity_after' => $item->getQuantityAfter(), 'quantity_difference' => $item->getQuantityDifference(), 'unit_cost' => $item->getUnitCost(), 'total' => $item->getTotal(), 'classification' => $item->getClassification()?->value, 'adjustment_reason' => $item->getAdjustmentReason()?->value])->values()->all(); }

        return $record; }
}
