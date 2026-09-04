<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Domain\Workforce\ShiftCoverageReadService;
use App\Domain\Workforce\ShiftOverviewService;
use App\Models\Shift;
use App\Models\Worker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadShiftsTool extends AbstractReadResourceTool
{
    /**
     * Return the stable provider-facing tool name.
     */
    public function name(): string
    {
        return 'read_shifts';
    }

    /**
     * Describe the bounded shift query.
     */
    public function description(): string
    {
        return 'Read scheduled shifts with workers, rates, duration, monthly worker totals, attendance ratings, and exact daily coverage. Opening coverage is conclusive only when a required time range is supplied.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'request' => $schema->anyOf([
                $schema->object([
                    'operation' => $schema->string()->enum(['list'])->required(),
                    'store_id' => $schema->integer()->description('Optional owned store filter.'),
                    'worker_id' => $schema->integer(),
                    'year' => $schema->integer()->min(2000)->max(2100),
                    'month' => $schema->integer()->min(1)->max(12),
                    'date_from' => $schema->string(),
                    'date_to' => $schema->string(),
                    'limit' => $schema->integer()->min(1)->max(50),
                    'cursor' => $schema->string(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'operation' => $schema->string()->enum(['summary'])->required(),
                    'store_id' => $schema->integer()->description('Optional owned store filter.'),
                    'worker_id' => $schema->integer(),
                    'year' => $schema->integer()->min(2000)->max(2100),
                    'month' => $schema->integer()->min(1)->max(12),
                    'date_from' => $schema->string(),
                    'date_to' => $schema->string(),
                    'required_start_time' => $schema->string(),
                    'required_end_time' => $schema->string(),
                ])->withoutAdditionalProperties(),
                $schema->object([
                    'operation' => $schema->string()->enum(['detail'])->required(),
                    'id' => $schema->integer()->required(),
                ])->withoutAdditionalProperties(),
            ])->required(),
        ];
    }

    /**
     * Return the query-service resource identifier.
     */
    protected function resource(): string
    {
        return 'shifts';
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
            $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('A shift identifier is required.'); }
            $query = Shift::query();
            Shift::scopeForUser($query, $this->actor->resolveScopeUser());

            return $this->detailResult($request, 'shifts', $this->record($query->findOrFail($id)));
        }
        $query = $this->query($request);
        if ($operation === 'summary') {
            $shifts = $query->orderBy('date')->orderBy('start_time')->get();
            $summary = Resolver::resolve(ShiftCoverageReadService::class)->summarize($shifts, $request);
            $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
            if ($storeId !== null) {
                $workers = Worker::query();
                Worker::scopeForUser($workers, $this->actor->resolveScopeUser());
                $overview = Resolver::resolve(ShiftOverviewService::class)->build($this->actor->resolveScopeUser(), $this->ownedStore($storeId, true), $shifts, $workers->get(), true);
                $summary = [...$summary, ...$overview];
            }

            return $this->summaryResult($request, 'shifts', $summary, $shifts->isEmpty() ? 'NO_SCHEDULED_SHIFTS' : null);
        }
        if ($operation !== 'list') { throw new InvalidArgumentException('Unknown shift read operation.'); }

        return $this->paginateById($query, $request, 'shifts', $request, fn(Shift $shift): array => $this->record($shift));
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return Builder<Shift>
     */
    private function query(array $request): Builder
    {
        $query = Shift::query();
        Shift::scopeForUser($query, $this->actor->resolveScopeUser());
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) { $this->ownedStore($storeId);
            Shift::scopeForStore($query, $storeId); }
        $workerId = Typer::parseNullableInt($request['worker_id'] ?? null);
        if ($workerId !== null) { Shift::scopeForWorker($query, $workerId); }
        $year = Typer::parseNullableInt($request['year'] ?? null);
        $month = Typer::parseNullableInt($request['month'] ?? null);
        if ($year !== null && $month !== null) { Shift::scopeForMonth($query, $year, $month); }
        $from = Typer::parseNullableString($request['date_from'] ?? null);
        if ($from !== null) { $query->whereDate('date', '>=', $from); }
        $to = Typer::parseNullableString($request['date_to'] ?? null);
        if ($to !== null) { $query->whereDate('date', '<=', $to); }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function record(Shift $shift): array
    {
        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $this->actor->resolveScopeUser());
        $worker = $workerQuery->find($shift->getWorkerId());

        return ['id' => $shift->getKey(), 'store_id' => $shift->getStoreId(), 'worker_id' => $shift->getWorkerId(), 'worker_name' => $worker instanceof Worker ? $worker->getFullName() : null, 'date' => $shift->getDate(), 'start_time' => $shift->getStartTimeShort(), 'end_time' => $shift->getEndTimeShort(), 'duration_minutes' => $shift->getDurationMinutes(), 'hourly_rate' => $shift->getHourlyRate(), 'url' => Resolver::resolveUrlGenerator()->route('shifts.index', ['store_id' => $shift->getStoreId()])];
    }
}
