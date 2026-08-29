<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Store;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadStoresTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_stores'; }

    /**
     * Explain the store facts available to the model.
     */
    public function description(): string { return 'Read owned stores with status, retail or warehouse type, address, operational notes, and safe integration state.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $filters = [
            'search' => $schema->string(),
            'status' => $schema->string()->enum(['active', 'inactive']),
            'type' => $schema->string()->enum(['retail', 'warehouse']),
        ];

        return ['request' => $schema->anyOf([
            $schema->object(['operation' => $schema->string()->enum(['list'])->required(), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), ...$filters])->withoutAdditionalProperties(),
        ])->required()];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array
    {
        $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        if ($operation === 'detail') {
            return $this->detailResult($request, 'stores', $this->record($this->ownedStore(Typer::parseInt($request['id'] ?? null))));
        }
        $query = Store::query();
        Store::scopeForUser($query, $this->actor->resolveScopeUser());
        $this->filters($query, $request);
        if ($operation === 'summary') {
            $rows = $query->get();

            return $this->summaryResult($request, 'stores', [
                'store_count' => $rows->count(),
                'active_count' => $rows->filter(static fn(Store $store): bool => $store->getStatus()->value === 'active')->count(),
                'inactive_count' => $rows->filter(static fn(Store $store): bool => $store->getStatus()->value !== 'active')->count(),
                'retail_count' => $rows->filter(static fn(Store $store): bool => !$store->isWarehouse())->count(),
                'warehouse_count' => $rows->filter(static fn(Store $store): bool => $store->isWarehouse())->count(),
            ], $rows->isEmpty() ? 'NO_MATCHING_DATA' : null);
        }
        if ($operation !== 'list') { throw new InvalidArgumentException('Unknown store read operation.'); }

        return $this->paginateById($query, $request, 'stores', $request, fn(Store $store): array => $this->record($store));
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'stores'; }

    /**
     * @param Builder<Store> $query
     * @param array<string, mixed> $request
     */
    private function filters(Builder $query, array $request): void
    {
        $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') { Store::scopeSearch($query, \mb_trim($search)); }
        $status = Typer::parseNullableString($request['status'] ?? null);
        if ($status !== null) { $query->where('status', $status); }
        $type = Typer::parseNullableString($request['type'] ?? null);
        if ($type !== null) { $query->where('is_warehouse', $type === 'warehouse'); }
    }

    /**
     * @return array<string, mixed>
     */
    private function record(Store $store): array
    {
        return [
            'id' => $store->getKey(), 'name' => $store->getName(), 'address' => $store->getAddress(),
            'status' => $store->getStatus()->value, 'type' => $store->isWarehouse() ? 'warehouse' : 'retail',
            'notes' => $store->getNotes(), 'slack_configured' => $store->getSlackChannel() !== null,
            'checklists_initialized' => $store->getChecklistsInitializedAt() !== null,
            'url' => Resolver::resolveUrlGenerator()->route('stores.show', $store->getKey()),
        ];
    }
}
