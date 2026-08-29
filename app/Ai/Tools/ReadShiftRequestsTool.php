<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ShiftRequest;
use App\Models\ShiftRequestMonthLock;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadShiftRequestsTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_shift_requests'; }

    /**
     * Explain the shift-request datasets available to the model.
     */
    public function description(): string { return 'Read worker shift requests or request month locks with store, worker, dates, requested times, and exact lifecycle counts.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $filters = ['dataset' => $schema->string()->enum(['requests', 'month_locks'])->required(), 'store_id' => $schema->integer(), 'worker_id' => $schema->integer(), 'year' => $schema->integer()->min(2000)->max(2100), 'month' => $schema->integer()->min(1)->max(12)];

        return ['request' => $schema->anyOf([
            $schema->object(['operation' => $schema->string()->enum(['list'])->required(), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(),
            $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'dataset' => $schema->string()->enum(['requests', 'month_locks'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(),
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
        $dataset = $this->dataset($request);
        if ($dataset === 'requests') {
            return $this->requests($request, $operation);
        }
        if ($dataset === 'month_locks') {
            return $this->monthLocks($request, $operation);
        }

        throw new InvalidArgumentException('Unknown shift request dataset.');
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'shift_requests'; }

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string { return Typer::parseNullableString($request['dataset'] ?? null) ?? 'requests'; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function requests(array $request, string $operation): array
    {
        $query = ShiftRequest::query();
        $query->where('user_id', $this->actor->resolveScopeUser()->getKey());
        $this->applyCommonFilters($query, $request);
        $workerId = Typer::parseNullableInt($request['worker_id'] ?? null);
        if ($workerId !== null) {
            $query->where('worker_id', $workerId);
        }
        $year = Typer::parseNullableInt($request['year'] ?? null);
        if ($year !== null) {
            $query->whereYear('date', $year);
        }
        $month = Typer::parseNullableInt($request['month'] ?? null);
        if ($month !== null) {
            $query->whereMonth('date', $month);
        }

        if ($operation === 'summary') {
            $count = $query->count();

            return $this->summaryResult($request, 'requests', ['request_count' => $count], $count === 0 ? 'NO_MATCHING_DATA' : null);
        }
        if ($operation === 'detail') {
            return $this->detailResult($request, 'requests', $this->requestRecord($query->findOrFail($this->requiredId($request))));
        }
        if ($operation !== 'list') {
            throw new InvalidArgumentException('Unknown shift request read operation.');
        }

        return $this->paginateById($query, $request, 'requests', $request, fn(ShiftRequest $item): array => $this->requestRecord($item));
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function monthLocks(array $request, string $operation): array
    {
        $query = ShiftRequestMonthLock::query();
        $query->where('user_id', $this->actor->resolveScopeUser()->getKey());
        $this->applyCommonFilters($query, $request);
        foreach (['year', 'month'] as $column) {
            $value = Typer::parseNullableInt($request[$column] ?? null);
            if ($value !== null) {
                $query->where($column, $value);
            }
        }
        if ($operation === 'summary') {
            $count = $query->count();

            return $this->summaryResult($request, 'month_locks', ['locked_month_count' => $count], $count === 0 ? 'NO_MATCHING_DATA' : null);
        }
        if ($operation === 'detail') {
            return $this->detailResult($request, 'month_locks', $this->lockRecord($query->findOrFail($this->requiredId($request))));
        }
        if ($operation !== 'list') {
            throw new InvalidArgumentException('Unknown shift request read operation.');
        }

        return $this->paginateById($query, $request, 'month_locks', $request, fn(ShiftRequestMonthLock $item): array => $this->lockRecord($item));
    }

    /**
     * @template TModel of ShiftRequest|ShiftRequestMonthLock
     *
     * @param Builder<TModel> $query
     * @param array<string, mixed> $request
     */
    private function applyCommonFilters(Builder $query, array $request): void
    {
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) {
            $this->ownedStore($storeId);
            $query->where('store_id', $storeId);
        }
    }

    /**
     * @param array<string, mixed> $request
     */
    private function requiredId(array $request): int
    {
        $id = Typer::parseNullableInt($request['id'] ?? null);
        if ($id === null) {
            throw new InvalidArgumentException('A shift request identifier is required.');
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestRecord(ShiftRequest $item): array { return ['id' => $item->getKey(), 'store_id' => $item->getStoreId(), 'worker_id' => $item->getWorkerId(), 'date' => $item->getDate(), 'start_time' => $item->getStartTimeShort(), 'end_time' => $item->getEndTimeShort(), 'url' => Resolver::resolveUrlGenerator()->route('shifts.index', ['store_id' => $item->getStoreId()])]; }

    /**
     * @return array<string, mixed>
     */
    private function lockRecord(ShiftRequestMonthLock $item): array { return ['id' => $item->getKey(), 'store_id' => $item->getStoreId(), 'year' => $item->getYear(), 'month' => $item->getMonth(), 'locked_at' => $item->getLockedAt()->toJSON(), 'locked_by_user_id' => $item->getLockedByUserId(), 'url' => Resolver::resolveUrlGenerator()->route('shifts.index', ['store_id' => $item->getStoreId()])]; }
}
