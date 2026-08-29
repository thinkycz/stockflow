<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\InventorySession;
use App\Services\InventorySessionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadInventoryCountsTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_inventory_counts'; }

    /**
     * Explain the inventory-count facts available to the model.
     */
    public function description(): string { return 'Read inventory count drafts and closed sessions with counted rows, expected quantities, differences, classifications, notes, dates, and exact totals.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array { $filters = ['store_id' => $schema->integer(), 'status' => $schema->string()->enum(['draft', 'closed']), 'date_from' => $schema->string(), 'date_to' => $schema->string()];

        return ['request' => $schema->anyOf([$schema->object(['operation' => $schema->string()->enum(['list'])->required(), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), ...$filters])->withoutAdditionalProperties()])->required()]; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array { $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        $query = InventorySession::query()->with(['items', 'store']);
        InventorySession::scopeForUser($query, $this->actor->resolveScopeUser());
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) { $this->ownedStore($storeId);
            InventorySession::scopeForStore($query, $storeId); } $status = Typer::parseNullableString($request['status'] ?? null);
        if ($status !== null) { $query->where('status', $status); } $from = Typer::parseNullableString($request['date_from'] ?? null);
        if ($from !== null) { $query->where('counted_at', '>=', $from . ' 00:00:00'); } $to = Typer::parseNullableString($request['date_to'] ?? null);
        if ($to !== null) { $query->where('counted_at', '<=', $to . ' 23:59:59'); } if ($operation === 'detail') { $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('An inventory session identifier is required.'); } $session = $query->findOrFail($id);

            return $this->detailResult($request, 'sessions', [...$this->record($session), 'rows' => Resolver::resolve(InventorySessionService::class)->buildSessionView($this->actor->resolveScopeUser(), $session)]); } if ($operation === 'summary') { $sessions = $query->get();

                return $this->summaryResult($request, 'sessions', ['session_count' => $sessions->count(), 'draft_count' => $sessions->filter(static fn(InventorySession $session): bool => $session->getStatus() === 'draft')->count(), 'closed_count' => $sessions->filter(static fn(InventorySession $session): bool => $session->getStatus() === 'closed')->count(), 'counted_row_count' => $sessions->sum(static fn(InventorySession $session): int => $session->getItems()->count())], $sessions->isEmpty() ? 'NO_MATCHING_DATA' : null); } if ($operation !== 'list') { throw new InvalidArgumentException('Unknown inventory count read operation.'); }

        return $this->paginateById($query, $request, 'sessions', $request, fn(InventorySession $session): array => $this->record($session)); }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'inventory_counts'; }

    /**
     * @return array<string, mixed>
     */
    private function record(InventorySession $session): array { return ['id' => $session->getKey(), 'store_id' => $session->getStore()->getKey(), 'store_name' => $session->getStore()->getName(), 'status' => $session->getStatus(), 'started_at' => $session->getStartedAt()->toJSON(), 'counted_at' => $session->getCountedAt()->toJSON(), 'rows_count' => $session->getItems()->count(), 'note' => $session->getNote(), 'created_by' => $session->getCreatedBy(), 'url' => Resolver::resolveUrlGenerator()->route('inventory-counts.index', ['store_id' => $session->getStore()->getKey()])]; }
}
