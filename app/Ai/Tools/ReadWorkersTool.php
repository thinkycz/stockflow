<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Worker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadWorkersTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_workers'; }

    /**
     * Explain the worker facts available to the model.
     */
    public function description(): string { return 'Read workers with names, hourly rates, calendar colors, and attendance-rating configuration. Use summary for exact wage-rate and rating-state aggregates.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $filters = ['search' => $schema->string(), 'attendance_rating_enabled' => $schema->boolean()];

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
        if ($operation === 'detail') { return $this->detailResult($request, 'workers', $this->record($this->worker(Typer::parseNullableInt($request['id'] ?? null)))); }
        $query = Worker::query();
        Worker::scopeForUser($query, $this->actor->resolveScopeUser());
        $this->filters($query, $request);
        if ($operation === 'summary') {
            $workers = $query->get();
            $rates = $workers->map(static fn(Worker $worker): float => $worker->getHourlyRate());

            return $this->summaryResult($request, 'workers', [
                'worker_count' => $workers->count(),
                'attendance_rating_enabled_count' => $workers->filter(static fn(Worker $worker): bool => $worker->isAttendanceRatingEnabled())->count(),
                'hourly_rate' => ['minimum' => $rates->min(), 'maximum' => $rates->max(), 'average' => $rates->isEmpty() ? null : \round((float) $rates->average(), 2)],
            ], $workers->isEmpty() ? 'NO_MATCHING_DATA' : null);
        }
        if ($operation !== 'list') { throw new InvalidArgumentException('Unknown worker read operation.'); }

        return $this->paginateById($query, $request, 'workers', $request, fn(Worker $worker): array => $this->record($worker));
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'workers'; }

    /**
     * Resolve one tenant-scoped worker.
     */
    private function worker(int|null $id): Worker
    {
        if ($id === null) { throw new InvalidArgumentException('A worker identifier is required.'); }
        $query = Worker::query();
        Worker::scopeForUser($query, $this->actor->resolveScopeUser());

        return $query->findOrFail($id);
    }

    /**
     * @param Builder<Worker> $query
     * @param array<string, mixed> $request
     */
    private function filters(Builder $query, array $request): void
    {
        $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') { Worker::scopeSearch($query, \mb_trim($search)); }
        if (\array_key_exists('attendance_rating_enabled', $request)) { $query->where('attendance_rating_enabled', (bool) $request['attendance_rating_enabled']); }
    }

    /**
     * @return array<string, mixed>
     */
    private function record(Worker $worker): array
    {
        return [
            'id' => $worker->getKey(), 'first_name' => $worker->getFirstName(), 'last_name' => $worker->getLastName(),
            'name' => $worker->getFullName(), 'hourly_rate' => $worker->getHourlyRate(),
            'attendance_rating_enabled' => $worker->isAttendanceRatingEnabled(), 'calendar_color' => $worker->getCalendarColor(),
            'url' => Resolver::resolveUrlGenerator()->route('workers.edit', $worker->getKey()),
        ];
    }
}
