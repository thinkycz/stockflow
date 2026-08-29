<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ChecklistDay;
use App\Models\ChecklistItem;
use App\Models\Worker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadChecklistsTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string
    {
        return 'read_checklists';
    }

    /**
     * Explain the checklist datasets available to the model.
     */
    public function description(): string
    {
        return 'Read checklist days or individual checklist items with dates, shifts, completion state, assigned workers, excuses, and exact completion totals.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $filters = [
            'dataset' => $schema->string()->enum(['days', 'items'])->required(),
            'store_id' => $schema->integer(),
            'date_from' => $schema->string(),
            'date_to' => $schema->string(),
            'status' => $schema->string()->enum(['complete', 'incomplete', 'excused']),
            'worker_id' => $schema->integer(),
            'shift' => $schema->string()->enum(['morning', 'afternoon']),
            'search' => $schema->string(),
        ];

        return ['request' => $schema->anyOf([
            $schema->object([
                'operation' => $schema->string()->enum(['list'])->required(),
                ...$filters,
                'limit' => $schema->integer()->min(1)->max(50),
                'cursor' => $schema->string(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['detail'])->required(),
                'dataset' => $schema->string()->enum(['days', 'items'])->required(),
                'id' => $schema->integer()->required(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['summary'])->required(),
                ...$filters,
            ])->withoutAdditionalProperties(),
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

        return match ($this->dataset($request)) {
            'days' => $this->days($request, $operation),
            'items' => $this->items($request, $operation),
            default => throw new InvalidArgumentException('Unknown checklist dataset.'),
        };
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string
    {
        return 'checklists';
    }

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string
    {
        return Typer::parseNullableString($request['dataset'] ?? null) ?? 'days';
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function days(array $request, string $operation): array
    {
        $query = ChecklistDay::query()->with('items');
        ChecklistDay::scopeForUser($query, $this->actor->resolveScopeUser());
        $this->applyDayFilters($query, $request);

        if ($operation === 'detail') {
            return $this->detailResult($request, 'days', $this->dayRecord($query->findOrFail($this->requiredId($request)), true));
        }

        if ($operation === 'summary') {
            $dayCount = (clone $query)->count();
            $itemQuery = $this->itemQuery($request);

            return $this->summaryResult($request, 'days', [
                'day_count' => $dayCount,
                'excused_day_count' => (clone $query)->whereNotNull('excused_at')->count(),
                'item_count' => (clone $itemQuery)->count(),
                'completed_count' => (clone $itemQuery)->whereNotNull('completed_at')->count(),
                'incomplete_count' => (clone $itemQuery)->whereNull('completed_at')->count(),
            ], $dayCount === 0 ? 'NO_MATCHING_DATA' : null);
        }

        if ($operation !== 'list') {
            throw new InvalidArgumentException('Unknown checklist day operation.');
        }

        return $this->paginateById($query, $request, 'days', $request, fn(ChecklistDay $day): array => $this->dayRecord($day, false));
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function items(array $request, string $operation): array
    {
        $query = $this->itemQuery($request);

        if ($operation === 'detail') {
            return $this->detailResult($request, 'items', $this->itemRecord($query->findOrFail($this->requiredId($request))));
        }

        if ($operation === 'summary') {
            $count = (clone $query)->count();

            return $this->summaryResult($request, 'items', [
                'item_count' => $count,
                'completed_count' => (clone $query)->whereNotNull('completed_at')->count(),
                'incomplete_count' => (clone $query)->whereNull('completed_at')->count(),
            ], $count === 0 ? 'NO_MATCHING_DATA' : null);
        }

        if ($operation !== 'list') {
            throw new InvalidArgumentException('Unknown checklist item operation.');
        }

        return $this->paginateById($query, $request, 'items', $request, fn(ChecklistItem $item): array => $this->itemRecord($item));
    }

    /**
     * @param Builder<ChecklistDay> $query
     * @param array<string, mixed> $request
     */
    private function applyDayFilters(Builder $query, array $request): void
    {
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) {
            $this->ownedStore($storeId);
            $query->where('store_id', $storeId);
        }
        $from = Typer::parseNullableString($request['date_from'] ?? null);
        if ($from !== null) {
            $query->whereDate('date', '>=', $from);
        }
        $to = Typer::parseNullableString($request['date_to'] ?? null);
        if ($to !== null) {
            $query->whereDate('date', '<=', $to);
        }
        $workerId = Typer::parseNullableInt($request['worker_id'] ?? null);
        if ($workerId !== null) {
            $query->whereHas('items', static fn(Builder $items): Builder => $items->where('completed_by_worker_id', $workerId));
        }
        $shift = Typer::parseNullableString($request['shift'] ?? null);
        if ($shift !== null) {
            $query->whereHas('items', static fn(Builder $items): Builder => $items->where('shift', $shift));
        }
        $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') {
            $query->whereHas('items', static fn(Builder $items): Builder => $items->where('text', 'like', '%' . \mb_trim($search) . '%'));
        }
        match (Typer::parseNullableString($request['status'] ?? null)) {
            'excused' => $query->whereNotNull('excused_at'),
            'complete' => $query->whereNull('excused_at')->whereDoesntHave('items', static fn(Builder $items): Builder => $items->whereNull('completed_at')),
            'incomplete' => $query->whereNull('excused_at')->whereHas('items', static fn(Builder $items): Builder => $items->whereNull('completed_at')),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return Builder<ChecklistItem>
     */
    private function itemQuery(array $request): Builder
    {
        $query = ChecklistItem::query()->with(['day', 'completedByWorker']);
        $ownerId = $this->actor->resolveScopeUser()->getKey();
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) {
            $this->ownedStore($storeId);
        }
        $from = Typer::parseNullableString($request['date_from'] ?? null);
        $to = Typer::parseNullableString($request['date_to'] ?? null);
        $status = Typer::parseNullableString($request['status'] ?? null);
        $query->whereHas('day', static function (Builder $days) use ($ownerId, $storeId, $from, $to, $status): void {
            $days->where('user_id', $ownerId);
            if ($storeId !== null) {
                $days->where('store_id', $storeId);
            }
            if ($from !== null) {
                $days->whereDate('date', '>=', $from);
            }
            if ($to !== null) {
                $days->whereDate('date', '<=', $to);
            }
            if ($status === 'excused') {
                $days->whereNotNull('excused_at');
            }
        });
        $workerId = Typer::parseNullableInt($request['worker_id'] ?? null);
        if ($workerId !== null) {
            $query->where('completed_by_worker_id', $workerId);
        }
        $shift = Typer::parseNullableString($request['shift'] ?? null);
        if ($shift !== null) {
            $query->where('shift', $shift);
        }
        $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') {
            ChecklistItem::scopeSearch($query, \mb_trim($search));
        }
        if ($status === 'complete') {
            $query->whereNotNull('completed_at');
        } elseif ($status === 'incomplete') {
            $query->whereNull('completed_at');
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function dayRecord(ChecklistDay $day, bool $includeItems): array
    {
        $items = $day->getItems();
        $record = [
            'id' => $day->getKey(),
            'store_id' => $day->getStoreId(),
            'date' => $day->getDate()->toDateString(),
            'excused' => $day->isExcused(),
            'excuse_reason' => $day->getExcuseReason(),
            'item_count' => $items->count(),
            'completed_count' => $items->filter(static fn(ChecklistItem $item): bool => $item->isCompleted())->count(),
            'url' => Resolver::resolveUrlGenerator()->route('checklists.index', ['store_id' => $day->getStoreId()]),
        ];

        if ($includeItems) {
            $record['items'] = $items->map(fn(ChecklistItem $item): array => $this->itemRecord($item, $day))->values()->all();
        }

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    private function itemRecord(ChecklistItem $item, ChecklistDay|null $knownDay = null): array
    {
        $day = $knownDay ?? $item->getDay();
        $item->loadMissing('completedByWorker');
        $worker = $item->getCompletedByWorker();

        return [
            'id' => $item->getKey(),
            'day_id' => $item->getChecklistDayId(),
            'store_id' => $day->getStoreId(),
            'date' => $day->getDate()->toDateString(),
            'shift' => $item->getShift()->value,
            'text' => $item->getText(),
            'position' => $item->getPosition(),
            'completed' => $item->isCompleted(),
            'completed_by_worker_id' => $item->getCompletedByWorkerId(),
            'completed_by_worker_name' => $worker instanceof Worker ? $worker->getFullName() : null,
            'completed_at' => $item->getCompletedAt()?->toJSON(),
            'excused' => $day->isExcused(),
            'url' => Resolver::resolveUrlGenerator()->route('checklists.index', ['store_id' => $day->getStoreId()]),
        ];
    }

    /**
     * @param array<string, mixed> $request
     */
    private function requiredId(array $request): int
    {
        $id = Typer::parseNullableInt($request['id'] ?? null);
        if ($id === null) {
            throw new InvalidArgumentException('A checklist identifier is required.');
        }

        return $id;
    }
}
