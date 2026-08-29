<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\NoticeboardCard;
use App\Services\NoticeboardContentSanitizer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadNoticeboardTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_noticeboard'; }

    /**
     * Explain the sanitized noticeboard facts available to the model.
     */
    public function description(): string { return 'Read active, expired, or trashed noticeboard cards with labels, expiry, and sanitized visible text. Binary images are never returned.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array { $filters = ['search' => $schema->string(), 'store_id' => $schema->integer(), 'lifecycle' => $schema->string()->enum(['active', 'expired', 'trash']), 'label' => $schema->string()];

        return ['request' => $schema->anyOf([$schema->object(['operation' => $schema->string()->enum(['list'])->required(), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'id' => $schema->integer()->required(), 'lifecycle' => $schema->string()->enum(['active', 'expired', 'trash'])])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), ...$filters])->withoutAdditionalProperties()])->required()]; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array
    {
        $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        $lifecycle = Typer::parseNullableString($request['lifecycle'] ?? null) ?? 'active';
        $query = $lifecycle === 'trash' ? NoticeboardCard::onlyTrashed() : NoticeboardCard::query();
        NoticeboardCard::scopeForUser($query, $this->actor->resolveScopeUser());
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) { $this->ownedStore($storeId);
            NoticeboardCard::scopeForStore($query, $storeId); }
        $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') { NoticeboardCard::scopeSearch($query, \mb_trim($search)); } $label = Typer::parseNullableString($request['label'] ?? null);
        if ($label !== null) { $query->where('label', $label); }
        if ($lifecycle === 'active') { $query->where(static fn($dates) => $dates->whereNull('expires_at')->orWhere('expires_at', '>=', Carbon::now())); } if ($lifecycle === 'expired') { $query->where('expires_at', '<', Carbon::now()); }
        if ($operation === 'summary') { $count = $query->count();

            return $this->summaryResult($request, 'cards', ['card_count' => $count], $count === 0 ? 'NO_MATCHING_DATA' : null); }
        if ($operation === 'detail') { $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('A noticeboard identifier is required.'); }

            return $this->detailResult($request, 'cards', $this->record($query->findOrFail($id), true)); }
        if ($operation !== 'list') { throw new InvalidArgumentException('Unknown noticeboard read operation.'); }

        return $this->paginateById($query, $request, 'cards', $request, fn(NoticeboardCard $card): array => $this->record($card, false));
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'noticeboard'; }

    /**
     * @return array<string, mixed>
     */
    private function record(NoticeboardCard $card, bool $includeText): array { $record = ['id' => $card->getKey(), 'store_id' => $card->getStoreId(), 'title' => $card->getTitle(), 'label' => $card->getLabel()->value, 'color' => $card->getColor()->value, 'size' => $card->getSize()->value, 'expires_at' => $card->getExpiresAt()?->toJSON(), 'deleted_at' => $card->getDeletedAt()?->toJSON(), 'has_image' => $card->getImagePath() !== null, 'binary_content_excluded' => $card->getImagePath() !== null, 'url' => Resolver::resolveUrlGenerator()->route('dashboard', ['store_id' => $card->getStoreId()])];
        if ($includeText) { $record['text'] = Resolver::resolve(NoticeboardContentSanitizer::class)->sanitize($card->getBodyHtml())['text']; }

        return $record; }
}
