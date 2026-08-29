<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Statement;
use App\Models\StatementDay;
use App\Services\StatementService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadStatementsTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string
    {
        return 'read_statements';
    }

    /**
     * Explain the statement datasets and business metrics available to the model.
     */
    public function description(): string
    {
        return 'Read monthly revenue statements, daily channel values, investment, provisions, gross margin, versions, and exact report totals. Use summary for analytical questions.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['request' => $schema->anyOf([
            $schema->object([
                'operation' => $schema->string()->enum(['list'])->required(),
                'dataset' => $schema->string()->enum(['reports', 'days'])->required(),
                'store_id' => $schema->integer(),
                'year' => $schema->integer()->min(2000)->max(2100),
                'month' => $schema->integer()->min(1)->max(12),
                'limit' => $schema->integer()->min(1)->max(50),
                'cursor' => $schema->string(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['detail'])->required(),
                'dataset' => $schema->string()->enum(['reports', 'days'])->required(),
                'id' => $schema->integer()->required(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['summary'])->required(),
                'dataset' => $schema->string()->enum(['reports'])->required(),
                'store_id' => $schema->integer(),
                'year' => $schema->integer()->min(2000)->max(2100),
                'month' => $schema->integer()->min(1)->max(12),
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

        if ($operation === 'summary') {
            $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
            if ($storeId !== null) {
                $this->ownedStore($storeId);
            }
            $summary = Resolver::resolve(StatementService::class)->buildReport(
                $this->actor->resolveScopeUser(),
                $storeId,
                Typer::parseNullableInt($request['year'] ?? null),
                Typer::parseNullableInt($request['month'] ?? null),
            );

            return $this->summaryResult($request, 'reports', $summary, $summary['days_with_revenue'] === 0 ? 'NO_REVENUE_DATA' : null);
        }

        if ($operation === 'detail') {
            if ($dataset === 'days') {
                $statements = Statement::query();
                Statement::scopeForUser($statements, $this->actor->resolveScopeUser());
                $id = Typer::parseNullableInt($request['id'] ?? null);
                if ($id === null) {
                    throw new InvalidArgumentException('A statement day identifier is required.');
                }
                $day = StatementDay::query()->whereIn('statement_id', $statements->select('id'))->findOrFail($id);

                return $this->detailResult($request, 'days', $this->dayRecord($day));
            }
            $statement = $this->statement(Typer::parseNullableInt($request['id'] ?? null));
            $report = Resolver::resolve(StatementService::class)->buildReport(
                $this->actor->resolveScopeUser(),
                $statement->getStoreId(),
                $statement->getYear(),
                $statement->getMonth(),
            );

            return $this->detailResult($request, 'reports', [
                ...$this->statementRecord($statement),
                'report' => $report,
                'versions' => Resolver::resolve(StatementService::class)->historyForStatement($statement, 50),
            ]);
        }

        if ($operation !== 'list') {
            throw new InvalidArgumentException('Unknown statement read operation.');
        }

        if ($dataset === 'days') {
            return $this->days($request);
        }
        if ($dataset !== 'reports') {
            throw new InvalidArgumentException('Unknown statement dataset.');
        }

        $query = Statement::query()->with('store');
        Statement::scopeForUser($query, $this->actor->resolveScopeUser());
        $this->applyPeriodFilters($query, $request);

        return $this->paginateById($query, $request, 'reports', $request, fn(Statement $statement): array => $this->statementRecord($statement));
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string
    {
        return 'statements';
    }

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string
    {
        return Typer::parseNullableString($request['dataset'] ?? null) ?? 'reports';
    }

    /**
     * Resolve one tenant-scoped statement.
     */
    private function statement(int|null $id): Statement
    {
        if ($id === null) {
            throw new InvalidArgumentException('A statement identifier is required.');
        }
        $query = Statement::query()->with('store');
        Statement::scopeForUser($query, $this->actor->resolveScopeUser());

        return $query->findOrFail($id);
    }

    /**
     * @param Builder<Statement> $query
     * @param array<string, mixed> $request
     */
    private function applyPeriodFilters(Builder $query, array $request): void
    {
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) {
            $this->ownedStore($storeId);
            Statement::scopeForStore($query, $storeId);
        }
        $year = Typer::parseNullableInt($request['year'] ?? null);
        if ($year !== null) {
            $query->where('year', $year);
        }
        $month = Typer::parseNullableInt($request['month'] ?? null);
        if ($month !== null) {
            $query->where('month', $month);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function statementRecord(Statement $statement): array
    {
        return [
            'id' => $statement->getKey(),
            'store_id' => $statement->getStoreId(),
            'store_name' => $statement->getStore()->getName(),
            'year' => $statement->getYear(),
            'month' => $statement->getMonth(),
            'url' => Resolver::resolveUrlGenerator()->route('statements.index', ['store_id' => $statement->getStoreId(), 'year' => $statement->getYear(), 'month' => $statement->getMonth()]),
        ];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function days(array $request): array
    {
        $statements = Statement::query();
        Statement::scopeForUser($statements, $this->actor->resolveScopeUser());
        $this->applyPeriodFilters($statements, $request);
        $query = StatementDay::query()->whereIn('statement_id', $statements->select('id'));

        return $this->paginateById($query, $request, 'days', $request, fn(StatementDay $day): array => $this->dayRecord($day));
    }

    /**
     * @return array<string, mixed>
     */
    private function dayRecord(StatementDay $day): array
    {
        return [
            'id' => $day->getKey(),
            'statement_id' => $day->getStatementId(),
            'date' => $day->getDate(),
            'cash' => $day->getCash(),
            'card' => $day->getCard(),
            'wolt' => $day->getWolt(),
            'bolt' => $day->getBolt(),
            'bolt_cash' => $day->getBoltCash(),
            'foodora' => $day->getFoodora(),
            'total' => $day->getTotal(),
            'cash_checked' => $day->getCashChecked(),
        ];
    }
}
