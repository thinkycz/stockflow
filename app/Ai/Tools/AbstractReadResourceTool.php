<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\AssistantReadCursor;
use App\Ai\AssistantTurnCancellation;
use App\Enums\AssistantActionClassificationEnum;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;
use Throwable;

abstract class AbstractReadResourceTool implements AuditableAssistantTool, Tool
{
    private const int MAX_SCALAR_LENGTH = 4000;

    /**
     * Create a tenant-scoped read tool for one conversation.
     */
    public function __construct(
        protected readonly User $actor,
        protected readonly string $conversationId,
    ) {}

    /**
     * Execute a repairable read and serialize its bounded versioned envelope.
     */
    final public function handle(Request $request): string
    {
        Resolver::resolve(AssistantTurnCancellation::class)->ensureNotRequested();
        $arguments = Typer::assertStringKeyArray($request->all());
        $input = \is_array($arguments['request'] ?? null)
            ? Typer::assertStringKeyArray($arguments['request'])
            : $arguments;

        try {
            $result = $this->execute($input);
        } catch (HttpExceptionInterface|HttpResponseException|InvalidArgumentException|ModelNotFoundException|ValidationException $exception) {
            $result = $this->errorResult($input, $this->safeErrorCode($exception), $this->safeErrorMessage($exception));
        }

        return \json_encode(
            $this->boundResult($result),
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * Return the resource domain recorded in assistant audits.
     */
    final public function auditDomain(): string
    {
        return $this->resource();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    final public function auditOperation(array $arguments): string
    {
        $input = \is_array($arguments['request'] ?? null)
            ? Typer::assertStringKeyArray($arguments['request'])
            : $arguments;

        return Typer::parseNullableString($input['operation'] ?? null) ?? 'list';
    }

    /**
     * @param array<string, mixed> $arguments
     */
    final public function auditClassification(array $arguments): AssistantActionClassificationEnum
    {
        return AssistantActionClassificationEnum::READ;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{store_id: int|null, store_name: string|null, target_type: string|null, target_id: string|null}
     */
    final public function auditContext(array $arguments): array
    {
        $input = \is_array($arguments['request'] ?? null)
            ? Typer::assertStringKeyArray($arguments['request'])
            : $arguments;
        $storeId = Typer::parseNullableInt($input['store_id'] ?? null);
        $store = $storeId === null ? null : Store::query()
            ->where('user_id', $this->actor->resolveScopeUser()->getKey())
            ->whereKey($storeId)
            ->first();

        return [
            'store_id' => $store instanceof Store ? $store->getKey() : null,
            'store_name' => $store instanceof Store ? $store->getName() : null,
            'target_type' => $this->resource(),
            'target_id' => match (true) {
                \is_int($input['id'] ?? null) => (string) $input['id'],
                \is_string($input['id'] ?? null) => $input['id'],
                default => null,
            },
        ];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    abstract protected function execute(array $request): array;

    /**
     * Return the stable resource identifier for envelopes, cursors, and audits.
     */
    abstract protected function resource(): string;

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string
    {
        return Typer::parseNullableString($request['dataset'] ?? null) ?? 'default';
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $filters
     *
     * @return array{after: array<string, int|string|null>, as_of: string}
     */
    protected function cursorState(array $request, string $dataset, array $filters): array
    {
        $cursor = Typer::parseNullableString($request['cursor'] ?? null);

        return $cursor === null
            ? ['after' => [], 'as_of' => Carbon::now()->toJSON()]
            : Resolver::resolve(AssistantReadCursor::class)->decode($this->actor, $this->resource(), $dataset, $filters, $cursor);
    }

    /**
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     * @param array<string, mixed> $request
     * @param array<string, mixed> $filters
     * @param callable(TModel): array<string, mixed> $map
     *
     * @return array<string, mixed>
     */
    protected function paginateById(Builder $query, array $request, string $dataset, array $filters, callable $map, bool $descending = true): array
    {
        $state = $this->cursorState($request, $dataset, $filters);
        $hasCursor = Typer::parseNullableString($request['cursor'] ?? null) !== null;
        $snapshotMaxId = Typer::parseNullableInt($state['after']['snapshot_max_id'] ?? null);
        $snapshotCount = Typer::parseNullableInt($state['after']['snapshot_count'] ?? null);

        if (!$hasCursor) {
            $snapshot = clone $query;
            $snapshot->where('created_at', '<=', $state['as_of']);
            $snapshotMaxId = Typer::parseNullableInt($snapshot->max('id'));
            $snapshotCount = (clone $snapshot)->count();
        } elseif ($snapshotMaxId === null || $snapshotCount === null) {
            throw new InvalidArgumentException('The read cursor is missing its dataset snapshot.');
        } else {
            $changed = clone $query;
            $changed->where('created_at', '<=', $state['as_of'])->where('id', '<=', $snapshotMaxId);
            $inserted = clone $query;

            if (
                $inserted->where('id', '>', $snapshotMaxId)->exists() ||
                $snapshotCount !== $changed->count() ||
                (clone $query)->where('updated_at', '>', $state['as_of'])->exists()
            ) {
                return $this->dataChangedResult($request, $dataset, $state['as_of']);
            }
        }
        $query->where('created_at', '<=', $state['as_of']);
        if ($snapshotMaxId !== null) {
            $query->where('id', '<=', $snapshotMaxId);
        }
        $afterId = Typer::parseNullableInt($state['after']['id'] ?? null);
        if ($afterId !== null) {
            $query->where('id', $descending ? '<' : '>', $afterId);
        }

        $limit = $this->limit($request);
        if ($descending) {
            $query->orderByDesc('id');
        } else {
            $query->orderBy('id');
        }
        $models = $query->limit($limit + 1)->getModels();
        $hasMore = $limit < \count($models);
        $models = \array_slice($models, 0, $limit);
        $records = [];
        foreach ($models as $model) {
            $records[] = $map($model);
        }
        [$records, $sizeLimited] = $this->fitListRecords($records);
        $hasMore = $hasMore || $sizeLimited;
        $last = $models[\count($records) - 1] ?? null;
        $nextCursor = $hasMore && $last instanceof Model
            ? Resolver::resolve(AssistantReadCursor::class)->encode(
                $this->actor,
                $this->resource(),
                $dataset,
                $filters,
                [
                    'id' => Typer::assertInt($last->getKey()),
                    'snapshot_max_id' => $snapshotMaxId,
                    'snapshot_count' => $snapshotCount,
                ],
                $state['as_of'],
            )
            : null;

        return $this->successResult($request, $dataset, $state['as_of'], $records, null, !$hasMore, $nextCursor, $records === [] ? 'NO_MATCHING_DATA' : null);
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    protected function detailResult(array $request, string $dataset, array $record): array
    {
        return $this->successResult($request, $dataset, Carbon::now()->toJSON(), [$record], null, true, null, null);
    }

    /**
     * @param array<string, mixed> $request
     * @param list<array<string, mixed>> $records
     * @param array<string, mixed> $filters
     * @param array<string, int|string|null> $after
     *
     * @return array<string, mixed>
     */
    protected function listResult(array $request, string $dataset, array $records, array $filters, bool $hasMore, array $after): array
    {
        $state = $this->cursorState($request, $dataset, $filters);
        [$records, $sizeLimited] = $this->fitListRecords($records);
        $hasMore = $hasMore || $sizeLimited;
        if ($sizeLimited && $records !== []) {
            $lastRecord = $records[\array_key_last($records)];
            foreach ($after as $key => $value) {
                $recordKey = \array_key_exists($key, $lastRecord) ? $key : ($key === 'row_id' ? 'id' : null);
                if ($recordKey !== null && (\is_int($lastRecord[$recordKey]) || \is_string($lastRecord[$recordKey]) || $lastRecord[$recordKey] === null)) {
                    $after[$key] = $lastRecord[$recordKey];
                }
            }
        }
        $nextCursor = $hasMore
            ? Resolver::resolve(AssistantReadCursor::class)->encode(
                $this->actor,
                $this->resource(),
                $dataset,
                $filters,
                $after,
                $state['as_of'],
            )
            : null;

        return $this->successResult(
            $request,
            $dataset,
            $state['as_of'],
            $records,
            null,
            !$hasMore,
            $nextCursor,
            $records === [] ? 'NO_MATCHING_DATA' : null,
        );
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $summary
     *
     * @return array<string, mixed>
     */
    protected function summaryResult(array $request, string $dataset, array $summary, string|null $emptyReason = null): array
    {
        return $this->successResult($request, $dataset, Carbon::now()->toJSON(), [], $summary, true, null, $emptyReason);
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function dataChangedResult(array $request, string $dataset, string $asOf): array
    {
        return [
            'version' => 2,
            'ok' => false,
            'resource' => $this->resource(),
            'operation' => Typer::parseNullableString($request['operation'] ?? null) ?? 'list',
            'dataset' => $dataset,
            'as_of' => $asOf,
            'applied_filters' => $request,
            'returned_count' => 0,
            'complete' => false,
            'has_more' => false,
            'next_cursor' => null,
            'records' => [],
            'summary' => null,
            'data_quality' => ['state' => 'changed', 'empty_reason' => null],
            'warnings' => ['DATA_CHANGED'],
            'truncated_fields' => [],
            'error' => [
                'code' => 'DATA_CHANGED',
                'message' => 'Matching data changed during pagination. Restart the read without a cursor.',
                'repairable' => true,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    protected function rowsSnapshot(array $rows): string
    {
        return \hash('sha256', \json_encode($rows, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array{after: array<string, int|string|null>, as_of: string} $state
     */
    protected function snapshotChanged(array $state, string $snapshot): bool
    {
        $previous = Typer::parseNullableString($state['after']['snapshot'] ?? null);

        return $previous !== null && !\hash_equals($previous, $snapshot);
    }

    /**
     * Resolve an authorized store owned by the main administrator.
     */
    protected function ownedStore(int $storeId, bool $retailOnly = false): Store
    {
        $query = Store::query()->where('user_id', $this->actor->resolveScopeUser()->getKey())->whereKey($storeId);
        if ($retailOnly) {
            $query->where('is_warehouse', false);
        }
        $store = $query->first();
        if (!$store instanceof Store) {
            throw new InvalidArgumentException('The requested store does not exist or is not authorized.');
        }

        return $store;
    }

    /**
     * @param array<string, mixed> $request
     */
    protected function limit(array $request): int
    {
        return \min(\max(Typer::parseNullableInt($request['limit'] ?? null) ?? 20, 1), Config::inject()->assertInt('ai.assistant.tool_result_limit'));
    }

    /**
     * @param array<string, mixed> $request
     * @param list<array<string, mixed>> $records
     * @param array<string, mixed>|null $summary
     *
     * @return array<string, mixed>
     */
    private function successResult(array $request, string $dataset, string $asOf, array $records, array|null $summary, bool $complete, string|null $nextCursor, string|null $emptyReason): array
    {
        $filters = $request;
        unset($filters['operation'], $filters['dataset'], $filters['limit'], $filters['cursor'], $filters['id']);

        return [
            'version' => 2,
            'ok' => true,
            'resource' => $this->resource(),
            'operation' => Typer::parseNullableString($request['operation'] ?? null) ?? 'list',
            'dataset' => $dataset,
            'as_of' => $asOf,
            'applied_filters' => $filters,
            'returned_count' => \count($records),
            'complete' => $complete,
            'has_more' => !$complete,
            'next_cursor' => $nextCursor,
            'records' => $records,
            'summary' => $summary,
            'data_quality' => ['state' => $emptyReason === null ? ($complete ? 'complete' : 'partial') : 'empty', 'empty_reason' => $emptyReason],
            'warnings' => $complete ? [] : ['PARTIAL_RESULT'],
            'truncated_fields' => [],
        ];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function errorResult(array $request, string $code, string $message): array
    {
        return [
            'version' => 2,
            'ok' => false,
            'resource' => $this->resource(),
            'operation' => Typer::parseNullableString($request['operation'] ?? null) ?? 'list',
            'dataset' => $this->dataset($request),
            'as_of' => Carbon::now()->toJSON(),
            'applied_filters' => $request,
            'returned_count' => 0,
            'complete' => false,
            'has_more' => false,
            'next_cursor' => null,
            'records' => [],
            'summary' => null,
            'data_quality' => ['state' => 'error', 'empty_reason' => null],
            'warnings' => [$code],
            'truncated_fields' => [],
            'error' => ['code' => $code, 'message' => $message, 'repairable' => true],
        ];
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function boundResult(array $result): array
    {
        $truncated = [];
        $result = Typer::assertStringKeyArray(Typer::assertArray($this->boundValue($result, '', $truncated)));
        $maxBytes = Config::inject()->assertInt('ai.assistant.tool_result_max_bytes');
        foreach ([2000, 1000, 500, 250, 120, 60] as $scalarLimit) {
            if ($maxBytes >= $this->encodedBytes($result)) {
                break;
            }

            $result = Typer::assertStringKeyArray(Typer::assertArray($this->shrinkStrings($result, '', $scalarLimit, $truncated)));
        }

        $result['truncated_fields'] = \array_slice(\array_values(\array_unique([
            ...$this->stringList($result['truncated_fields'] ?? []),
            ...$truncated,
        ])), 0, Config::inject()->assertInt('ai.assistant.tool_result_limit'));

        if ($maxBytes < $this->encodedBytes($result)) {
            return $this->oversizedResult($result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function oversizedResult(array $result): array
    {
        return [
            'version' => 2,
            'ok' => false,
            'resource' => Typer::parseNullableString($result['resource'] ?? null) ?? $this->resource(),
            'operation' => Typer::parseNullableString($result['operation'] ?? null) ?? 'list',
            'dataset' => Typer::parseNullableString($result['dataset'] ?? null) ?? 'default',
            'as_of' => Typer::parseNullableString($result['as_of'] ?? null) ?? Carbon::now()->toJSON(),
            'applied_filters' => [],
            'returned_count' => 0,
            'complete' => false,
            'has_more' => false,
            'next_cursor' => null,
            'records' => [],
            'summary' => null,
            'data_quality' => ['state' => 'error', 'empty_reason' => null],
            'warnings' => ['RESULT_SIZE_LIMIT'],
            'truncated_fields' => [],
            'error' => [
                'code' => 'RESULT_SIZE_LIMIT',
                'message' => 'The result is too large. Narrow the requested filters or ask for a more specific summary.',
                'repairable' => true,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $records
     *
     * @return array{list<array<string, mixed>>, bool}
     */
    private function fitListRecords(array $records): array
    {
        $originalCount = \count($records);
        $budget = \max(1024, Config::inject()->assertInt('ai.assistant.tool_result_max_bytes') - 8192);
        while (\count($records) > 1 && $budget < \mb_strlen(\json_encode($records, \JSON_THROW_ON_ERROR), '8bit')) {
            \array_pop($records);
        }

        return [$records, $originalCount > \count($records)];
    }

    /**
     * @param list<string> $truncated
     */
    private function boundValue(mixed $value, string $path, array &$truncated): mixed
    {
        if (\is_string($value) && \mb_strlen($value) > self::MAX_SCALAR_LENGTH) {
            $truncated[] = $path;

            return \mb_substr($value, 0, self::MAX_SCALAR_LENGTH) . '…';
        }
        if (!\is_array($value)) {
            return $value;
        }
        $bounded = [];
        if (\array_is_list($value) && \count($value) > Config::inject()->assertInt('ai.assistant.tool_result_limit')) {
            $truncated[] = $path;
            $value = \array_slice($value, 0, Config::inject()->assertInt('ai.assistant.tool_result_limit'));
        }
        foreach ($value as $key => $item) {
            $bounded[$key] = $this->boundValue($item, $path === '' ? (string) $key : $path . '.' . $key, $truncated);
        }

        return $bounded;
    }

    /**
     * @param list<string> $truncated
     */
    private function shrinkStrings(mixed $value, string $path, int $limit, array &$truncated): mixed
    {
        if (\is_string($value)) {
            if ($path === 'next_cursor' || $limit >= \mb_strlen($value)) {
                return $value;
            }

            $truncated[] = $path;

            return \mb_substr($value, 0, $limit) . '…';
        }
        if (!\is_array($value)) {
            return $value;
        }

        $bounded = [];
        foreach ($value as $key => $item) {
            $nestedPath = $path === '' ? (string) $key : $path . '.' . $key;
            $bounded[$key] = $this->shrinkStrings($item, $nestedPath, $limit, $truncated);
        }

        return $bounded;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function encodedBytes(array $result): int
    {
        return \mb_strlen(\json_encode($result, \JSON_THROW_ON_ERROR), '8bit');
    }

    /**
     * Map expected read failures to safe repairable error codes.
     */
    private function safeErrorCode(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof ModelNotFoundException,
            $exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 404 => 'NOT_FOUND_OR_NOT_AUTHORIZED',
            $exception instanceof ValidationException => 'VALIDATION_FAILED',
            default => 'INVALID_REQUEST',
        };
    }

    /**
     * Map expected read failures to bounded model-facing messages.
     */
    private function safeErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            foreach (Typer::assertArray($exception->errors()) as $messages) {
                foreach (Typer::assertArray($messages) as $message) {
                    if (\is_string($message)) {
                        return $message;
                    }
                }
            }

            return 'The request is invalid.';
        }
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode() === 404 ? 'The requested data does not exist or is not authorized.' : 'The request could not be completed.';
        }

        return $exception->getMessage() !== '' ? $exception->getMessage() : 'The request is invalid.';
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return \array_values(\array_filter($value, static fn(mixed $item): bool => \is_string($item)));
    }
}
