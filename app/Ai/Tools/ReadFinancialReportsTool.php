<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\FinancialReport;
use App\Models\Store;
use App\Services\FinancialReportService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadFinancialReportsTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string
    {
        return 'read_financial_reports';
    }

    /**
     * Explain the financial-report datasets available to the model.
     */
    public function description(): string
    {
        return 'Read real income and expense report data, including revenue drivers, stock costs, payroll, recurring and manual rows, overrides, notes, profit, and lifecycle state. Use summary for analysis.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['request' => $schema->anyOf([
            $schema->object([
                'operation' => $schema->string()->enum(['list'])->required(),
                'dataset' => $schema->string()->enum(['reports', 'rows'])->required(),
                'store_id' => $schema->integer(),
                'year' => $schema->integer()->min(2000)->max(2100),
                'month' => $schema->integer()->min(1)->max(12),
                'direction' => $schema->string()->enum(['income', 'expense']),
                'status' => $schema->string()->enum(['open', 'closed']),
                'limit' => $schema->integer()->min(1)->max(50),
                'cursor' => $schema->string(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['detail'])->required(),
                'dataset' => $schema->string()->enum(['reports'])->required(),
                'id' => $schema->integer()->required(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['summary'])->required(),
                'dataset' => $schema->string()->enum(['reports'])->required(),
                'store_id' => $schema->integer()->required(),
                'year' => $schema->integer()->min(2000)->max(2100)->required(),
                'month' => $schema->integer()->min(1)->max(12)->required(),
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
            [$store, $year, $month] = $this->period($request);
            $report = Resolver::resolve(FinancialReportService::class)->build($this->actor->resolveScopeUser(), $store, $year, $month);

            return $this->summaryResult($request, 'reports', $report, $this->emptyReason($report));
        }

        if ($operation === 'detail') {
            $reportModel = $this->report(Typer::parseNullableInt($request['id'] ?? null));
            $store = $this->ownedStore($reportModel->getStoreId(), true);

            return $this->detailResult($request, 'reports', [
                ...$this->reportRecord($reportModel, $store),
                'data' => Resolver::resolve(FinancialReportService::class)->build(
                    $this->actor->resolveScopeUser(),
                    $store,
                    $reportModel->getYear(),
                    $reportModel->getMonth(),
                ),
            ]);
        }

        if ($operation !== 'list') {
            throw new InvalidArgumentException('Unknown financial report read operation.');
        }

        if ($dataset === 'rows') {
            return $this->rows($request);
        }
        if ($dataset !== 'reports') {
            throw new InvalidArgumentException('Unknown financial report dataset.');
        }

        $query = FinancialReport::query();
        FinancialReport::scopeForUser($query, $this->actor->resolveScopeUser());
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) {
            $this->ownedStore($storeId, true);
            $query->where('store_id', $storeId);
        }
        foreach (['year', 'month'] as $column) {
            $value = Typer::parseNullableInt($request[$column] ?? null);
            if ($value !== null) {
                $query->where($column, $value);
            }
        }
        $status = Typer::parseNullableString($request['status'] ?? null);
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $this->paginateById($query, $request, 'reports', $request, function (FinancialReport $report): array {
            return $this->reportRecord($report, $this->ownedStore($report->getStoreId(), true));
        });
    }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string
    {
        return 'financial_reports';
    }

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string
    {
        return Typer::parseNullableString($request['dataset'] ?? null) ?? 'reports';
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array{Store, int, int}
     */
    private function period(array $request): array
    {
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        $year = Typer::parseNullableInt($request['year'] ?? null);
        $month = Typer::parseNullableInt($request['month'] ?? null);
        if ($storeId === null || $year === null || $month === null) {
            throw new InvalidArgumentException('Store, year, and month are required for financial data.');
        }

        return [$this->ownedStore($storeId, true), $year, $month];
    }

    /**
     * Resolve one tenant-scoped financial report.
     */
    private function report(int|null $id): FinancialReport
    {
        if ($id === null) {
            throw new InvalidArgumentException('A financial report identifier is required.');
        }
        $query = FinancialReport::query();
        FinancialReport::scopeForUser($query, $this->actor->resolveScopeUser());

        return $query->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportRecord(FinancialReport $report, Store $store): array
    {
        return [
            'id' => $report->getKey(),
            'store_id' => $report->getStoreId(),
            'store_name' => $store->getName(),
            'year' => $report->getYear(),
            'month' => $report->getMonth(),
            'status' => $report->getStatus()->value,
            'closed_at' => $report->getClosedAt()?->toJSON(),
            'reopened_at' => $report->getReopenedAt()?->toJSON(),
            'url' => Resolver::resolveUrlGenerator()->route('income-expenses.index', ['store_id' => $report->getStoreId(), 'year' => $report->getYear(), 'month' => $report->getMonth()]),
        ];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function rows(array $request): array
    {
        [$store, $year, $month] = $this->period($request);
        $report = Resolver::resolve(FinancialReportService::class)->build($this->actor->resolveScopeUser(), $store, $year, $month);
        $direction = Typer::parseNullableString($request['direction'] ?? null);
        $rows = match ($direction) {
            'income' => Typer::assertArray($report['income_rows'] ?? []),
            'expense' => Typer::assertArray($report['expense_rows'] ?? []),
            null => [...Typer::assertArray($report['income_rows'] ?? []), ...Typer::assertArray($report['expense_rows'] ?? [])],
            default => throw new InvalidArgumentException('Unknown financial row direction.'),
        };
        $state = $this->cursorState($request, 'rows', $request);
        $records = [];
        foreach ($rows as $row) {
            $record = Typer::assertStringKeyArray(Typer::assertArray($row));
            $records[] = $record;
        }
        \usort($records, static fn(array $left, array $right): int => \strcmp(Typer::assertString($left['id'] ?? null), Typer::assertString($right['id'] ?? null)));
        $snapshot = $this->rowsSnapshot($records);
        if ($this->snapshotChanged($state, $snapshot)) {
            return $this->dataChangedResult($request, 'rows', $state['as_of']);
        }
        $after = Typer::parseNullableString($state['after']['row_id'] ?? null);
        $records = \array_values(\array_filter(
            $records,
            static fn(array $record): bool => $after === null || \strcmp(Typer::assertString($record['id'] ?? null), $after) > 0,
        ));
        $limit = $this->limit($request);
        $hasMore = $limit < \count($records);
        $records = \array_slice($records, 0, $limit);
        $last = $records === [] ? $after : Typer::assertString($records[\array_key_last($records)]['id'] ?? null);

        return $this->listResult($request, 'rows', $records, $request, $hasMore, ['row_id' => $last, 'snapshot' => $snapshot]);
    }

    /**
     * @param array<string, mixed> $report
     */
    private function emptyReason(array $report): string|null
    {
        $totals = Typer::assertStringKeyArray(Typer::assertArray($report['totals'] ?? []));

        return Typer::parseFloat($totals['income'] ?? 0) === 0.0 && Typer::parseFloat($totals['expenses'] ?? 0) === 0.0
            ? 'NO_FINANCIAL_ACTIVITY'
            : null;
    }
}
