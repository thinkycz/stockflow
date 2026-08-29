<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\OperationalDailyDigest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadSettingsTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string
    {
        return 'read_settings';
    }

    /**
     * Explain the safe settings datasets available to the model.
     */
    public function description(): string
    {
        return 'Read one safe settings dataset: administrator profile, Slack integration status, or daily digest delivery records and summaries. Secrets and credentials are excluded.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $digestFilters = [
            'status' => $schema->string()->enum(['pending', 'queued', 'sent', 'failed']),
            'date_from' => $schema->string(),
            'date_to' => $schema->string(),
        ];

        return ['request' => $schema->anyOf([
            $schema->object([
                'operation' => $schema->string()->enum(['list'])->required(),
                'dataset' => $schema->string()->enum(['profile', 'integrations'])->required(),
                'limit' => $schema->integer()->min(1)->max(1),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['summary'])->required(),
                'dataset' => $schema->string()->enum(['profile', 'integrations'])->required(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['list'])->required(),
                'dataset' => $schema->string()->enum(['digests'])->required(),
                ...$digestFilters,
                'limit' => $schema->integer()->min(1)->max(50),
                'cursor' => $schema->string(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['detail'])->required(),
                'dataset' => $schema->string()->enum(['digests'])->required(),
                'id' => $schema->integer()->required(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['summary'])->required(),
                'dataset' => $schema->string()->enum(['digests'])->required(),
                ...$digestFilters,
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
        $dataset = $this->dataset($request);

        if ($dataset === 'profile') {
            return $this->singleResult($request, $operation, 'profile', [
                'email' => $this->actor->getEmail(),
                'locale' => $this->actor->getLocale(),
                'active_store_id' => $this->actor->getActiveStoreId(),
                'url' => Resolver::resolveUrlGenerator()->route('settings.show'),
            ]);
        }

        if ($dataset === 'integrations') {
            return $this->singleResult($request, $operation, 'integrations', [
                'company_slack_channel' => $this->actor->getCompanySlackChannel(),
                'slack_configured' => $this->actor->getCompanySlackChannel() !== null,
                'operational_digest_started_on' => $this->actor->getOperationalDigestStartedOn()?->toDateString(),
                'url' => Resolver::resolveUrlGenerator()->route('settings.show'),
            ]);
        }

        if ($dataset !== 'digests') {
            throw new InvalidArgumentException('Unknown settings dataset.');
        }

        $query = OperationalDailyDigest::querySelect(OperationalDailyDigest::query())
            ->where('company_user_id', $this->actor->resolveScopeUser()->getKey());
        $this->applyDigestFilters($query, $request);

        if ($operation === 'detail') {
            $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) {
                throw new InvalidArgumentException('A digest identifier is required.');
            }

            return $this->detailResult($request, 'digests', $this->digestRecord($query->findOrFail($id), true));
        }

        if ($operation === 'summary') {
            $count = (clone $query)->count();
            $byStatus = (clone $query)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(static fn(mixed $value): int => Typer::parseInt($value))
                ->all();

            return $this->summaryResult($request, 'digests', [
                'digest_count' => $count,
                'activity_count' => Typer::parseInt((clone $query)->sum('activity_count')),
                'attempt_count' => Typer::parseInt((clone $query)->sum('attempt_count')),
                'by_status' => $byStatus,
            ], $count === 0 ? 'NO_MATCHING_DATA' : null);
        }

        if ($operation !== 'list') {
            throw new InvalidArgumentException('Unknown settings read operation.');
        }

        return $this->paginateById(
            $query,
            $request,
            'digests',
            $request,
            fn(OperationalDailyDigest $digest): array => $this->digestRecord($digest, false),
        );
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string
    {
        return 'settings';
    }

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string
    {
        return Typer::parseNullableString($request['dataset'] ?? null) ?? 'profile';
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function singleResult(array $request, string $operation, string $dataset, array $record): array
    {
        return match ($operation) {
            'list' => $this->detailResult($request, $dataset, $record),
            'summary' => $this->summaryResult($request, $dataset, $record),
            default => throw new InvalidArgumentException('Unknown settings read operation.'),
        };
    }

    /**
     * @param Builder<OperationalDailyDigest> $query
     * @param array<string, mixed> $request
     */
    private function applyDigestFilters(Builder $query, array $request): void
    {
        $status = Typer::parseNullableString($request['status'] ?? null);
        if ($status !== null) {
            $query->where('status', $status);
        }
        $from = Typer::parseNullableString($request['date_from'] ?? null);
        if ($from !== null) {
            $query->whereDate('digest_date', '>=', $from);
        }
        $to = Typer::parseNullableString($request['date_to'] ?? null);
        if ($to !== null) {
            $query->whereDate('digest_date', '<=', $to);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function digestRecord(OperationalDailyDigest $digest, bool $includeSnapshot): array
    {
        $record = [
            'id' => $digest->getKey(),
            'date' => $digest->getDigestDate()->toDateString(),
            'status' => $digest->getStatus()->value,
            'activity_count' => $digest->getActivityCount(),
            'attempt_count' => $digest->getAttemptCount(),
            'last_error' => $digest->getLastError(),
            'queued_at' => $digest->getQueuedAt()?->toJSON(),
            'sent_at' => $digest->getSentAt()?->toJSON(),
            'url' => Resolver::resolveUrlGenerator()->route('settings.slack-digests.show', $digest->getKey()),
        ];

        if ($includeSnapshot) {
            $record['snapshot'] = $digest->getSnapshot();
        }

        return $record;
    }
}
