<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ShiftShareLink;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadShiftShareLinksTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_shift_share_links'; }

    /**
     * Explain the redacted share-link lifecycle facts available to the model.
     */
    public function description(): string { return 'Read shift share-link lifecycle metadata by store. Bearer tokens and public token URLs are never returned.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array { $filters = ['store_id' => $schema->integer()];

        return ['request' => $schema->anyOf([$schema->object(['operation' => $schema->string()->enum(['list'])->required(), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), ...$filters])->withoutAdditionalProperties()])->required()]; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array
    {
        $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        $query = ShiftShareLink::query();
        ShiftShareLink::scopeForUser($query, $this->actor->resolveScopeUser());
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) { $this->ownedStore($storeId);
            ShiftShareLink::scopeForStore($query, $storeId); }
        if ($operation === 'summary') { $count = $query->count();

            return $this->summaryResult($request, 'share_links', ['link_count' => $count], $count === 0 ? 'NOT_CONFIGURED' : null); }
        if ($operation === 'detail') { $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('A share-link identifier is required.'); }

            return $this->detailResult($request, 'share_links', $this->record($query->findOrFail($id))); }
        if ($operation !== 'list') { throw new InvalidArgumentException('Unknown share-link read operation.'); }

        return $this->paginateById($query, $request, 'share_links', $request, fn(ShiftShareLink $link): array => $this->record($link));
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'shift_share_links'; }

    /**
     * @return array<string, mixed>
     */
    private function record(ShiftShareLink $link): array { return ['id' => $link->getKey(), 'store_id' => $link->getStoreId(), 'store_name' => $link->getStore()->getName(), 'name' => $link->getName(), 'token_redacted' => true]; }
}
