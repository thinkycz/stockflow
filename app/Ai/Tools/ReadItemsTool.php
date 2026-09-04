<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Domain\Inventory\InventoryReportService;
use App\Models\Item;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadItemsTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_items'; }

    /**
     * Explain the catalog and store-stock datasets available to the model.
     */
    public function description(): string { return 'Read catalog items or per-store stock with quantities, prices, value, consumption predictions, low-stock risk, and exact inventory totals.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array { $filters = ['dataset' => $schema->string()->enum(['catalog', 'store_stock'])->required(), 'search' => $schema->string(), 'store_id' => $schema->integer(), 'stock_status' => $schema->string()->enum(['in_stock', 'low_stock', 'out_of_stock', 'ok', 'due_soon', 'out', 'no_data'])];

        return ['request' => $schema->anyOf([$schema->object(['operation' => $schema->string()->enum(['list'])->required(), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'dataset' => $schema->string()->enum(['catalog'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), ...$filters, 'date_from' => $schema->string(), 'date_to' => $schema->string()])->withoutAdditionalProperties()])->required()]; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array
    {
        $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        $dataset = $this->dataset($request);
        if ($operation === 'summary' && $dataset === 'store_stock') {
            $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
            if ($storeId === null) { throw new InvalidArgumentException('A store is required for stock analysis.'); }
            $cutoff = Carbon::parse(Typer::parseNullableString($request['date_to'] ?? null) ?? Carbon::now()->toDateString())->endOfDay();
            $start = Carbon::parse(Typer::parseNullableString($request['date_from'] ?? null) ?? $cutoff->copy()->startOfMonth()->toDateString())->startOfDay();
            $report = Resolver::resolve(InventoryReportService::class)->build($this->actor->resolveScopeUser(), $this->ownedStore($storeId), $start, $cutoff);

            return $this->summaryResult($request, 'store_stock', $report, Typer::assertArray($report['items'] ?? []) === [] ? 'NO_STOCK_DATA' : null);
        }
        $query = Item::query();
        Item::scopeForUser($query, $this->actor->resolveScopeUser());
        $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') { Item::scopeSearch($query, \mb_trim($search)); }
        $stockStatus = Typer::parseNullableString($request['stock_status'] ?? null);
        if ($stockStatus !== null && $dataset === 'catalog') {
            $comparison = match ($stockStatus) {
                'in_stock' => ['>', Item::LOW_STOCK_THRESHOLD],
                'low_stock' => ['between', [1, Item::LOW_STOCK_THRESHOLD]],
                'out_of_stock' => ['<=', 0],
                default => null,
            };
            if ($comparison === null) { throw new InvalidArgumentException('The requested stock status applies only to per-store risk data.'); }
            $quantitySql = '(SELECT COALESCE(SUM(store_items.quantity), 0) FROM store_items INNER JOIN stores ON stores.id = store_items.store_id WHERE store_items.item_id = items.id AND stores.is_warehouse = 1)';
            if ($comparison[0] === 'between') { $bounds = Typer::assertArray($comparison[1]);
                $query->whereRaw($quantitySql . ' BETWEEN ? AND ?', [Typer::parseInt($bounds[0] ?? null), Typer::parseInt($bounds[1] ?? null)]); } else { $query->whereRaw($quantitySql . ' ' . $comparison[0] . ' ?', [Typer::parseInt($comparison[1])]); }
        }
        Item::querySelect($query);
        if ($operation === 'detail') { $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('An item identifier is required.'); }

            return $this->detailResult($request, 'catalog', $this->record($query->findOrFail($id))); }
        if ($operation === 'summary') { $items = $query->get();

            return $this->summaryResult($request, 'catalog', ['item_count' => $items->count(), 'total_quantity' => $items->sum(static fn(Item $item): float|int => $item->getTotalQuantity()), 'warehouse_quantity' => $items->sum(static fn(Item $item): int => $item->getWarehouseQuantity()), 'inventory_value' => \round($items->sum(static fn(Item $item): float => $item->getTotalValue()), 2)], $items->isEmpty() ? 'NO_MATCHING_DATA' : null); }
        if ($operation !== 'list') { throw new InvalidArgumentException('Unknown item read operation.'); }
        if ($dataset === 'store_stock') { return $this->storeStock($request); } if ($dataset !== 'catalog') { throw new InvalidArgumentException('Unknown item dataset.'); }

        return $this->paginateById($query, $request, 'catalog', $request, fn(Item $item): array => $this->record($item));
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'items'; }

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string { return Typer::parseNullableString($request['dataset'] ?? null) ?? 'catalog'; }

    /**
     * @return array<string, mixed>
     */
    private function record(Item $item): array { return ['id' => $item->getKey(), 'title' => $item->getTitle(), 'sku' => $item->getSku(), 'unit' => $item->getUnit(), 'description' => $item->getDescription(), 'purchase_price' => $item->getPurchasePrice(), 'total_quantity' => $item->getTotalQuantity(), 'warehouse_quantity' => $item->getWarehouseQuantity(), 'total_value' => $item->getTotalValue(), 'stock_status' => $item->getStockStatus()->value, 'url' => Resolver::resolveUrlGenerator()->route('items.show', $item->getKey())]; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function storeStock(array $request): array { $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId === null) { throw new InvalidArgumentException('A store is required for store stock.'); }
        $cutoff = Carbon::now()->endOfDay();
        $report = Resolver::resolve(InventoryReportService::class)->build($this->actor->resolveScopeUser(), $this->ownedStore($storeId), $cutoff->copy()->startOfMonth(), $cutoff);
        $rows = $this->rows(Typer::assertArray($report['items'] ?? []));
        $search = \mb_strtolower(Typer::parseNullableString($request['search'] ?? null) ?? '');
        if ($search !== '') { $rows = \array_values(\array_filter($rows, static fn(array $row): bool => \str_contains(\mb_strtolower(Typer::assertString($row['title'] ?? null)), $search) || \str_contains(\mb_strtolower(Typer::parseNullableString($row['sku'] ?? null) ?? ''), $search))); } $state = $this->cursorState($request, 'store_stock', $request);
        $stockStatus = Typer::parseNullableString($request['stock_status'] ?? null);
        if ($stockStatus !== null) {
            $rows = \array_values(\array_filter($rows, static function (array $row) use ($stockStatus): bool {
                $status = Typer::assertString($row['status'] ?? null);
                $quantity = Typer::parseFloat($row['current_quantity'] ?? 0);

                return match ($stockStatus) {
                    'in_stock' => $quantity > 0 && !\in_array($status, ['due_soon', 'out'], true),
                    'low_stock' => \in_array($status, ['due_soon', 'out'], true),
                    'out_of_stock' => $quantity <= 0,
                    default => $status === $stockStatus,
                };
            }));
        }
        $snapshot = $this->rowsSnapshot($rows);
        if ($this->snapshotChanged($state, $snapshot)) {
            return $this->dataChangedResult($request, 'store_stock', $state['as_of']);
        }
        $after = Typer::parseNullableInt($state['after']['item_id'] ?? null);
        $rows = \array_values(\array_filter($rows, static fn(array $row): bool => $after === null || $after < Typer::parseInt($row['item_id'] ?? null)));
        $limit = $this->limit($request);
        $hasMore = $limit < \count($rows);
        $rows = \array_slice($rows, 0, $limit);
        $last = $rows === [] ? $after : Typer::parseInt($rows[\array_key_last($rows)]['item_id'] ?? null);

        return $this->listResult($request, 'store_stock', $rows, $request, $hasMore, ['item_id' => $last, 'snapshot' => $snapshot]); }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $values): array
    {
        $rows = [];
        foreach ($values as $value) { $rows[] = Typer::assertStringKeyArray(Typer::assertArray($value)); }

        return $rows;
    }
}
