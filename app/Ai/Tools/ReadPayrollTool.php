<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Domain\Payroll\PayrollReportReadService;
use App\Models\PayrollReport;
use App\Models\Store;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadPayrollTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_payroll'; }

    /**
     * Explain the payroll datasets and facts available to the model.
     */
    public function description(): string { return 'Read payroll reports or payslips with workers, planned and actual hours, rates, overrides, adjustments, incomplete attendance, final amounts, and exact totals.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $period = [
            'store_id' => $schema->integer()->required(),
            'year' => $schema->integer()->min(2000)->max(2100)->required(),
            'month' => $schema->integer()->min(1)->max(12)->required(),
        ];

        return ['request' => $schema->anyOf([
            $schema->object([
                'operation' => $schema->string()->enum(['list'])->required(),
                'dataset' => $schema->string()->enum(['reports'])->required(),
                'store_id' => $schema->integer(),
                'year' => $schema->integer()->min(2000)->max(2100),
                'month' => $schema->integer()->min(1)->max(12),
                'status' => $schema->string()->enum(['open', 'closed']),
                'limit' => $schema->integer()->min(1)->max(50),
                'cursor' => $schema->string(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['list'])->required(),
                'dataset' => $schema->string()->enum(['payslips'])->required(),
                ...$period,
                'worker_id' => $schema->integer(),
                'limit' => $schema->integer()->min(1)->max(50),
                'cursor' => $schema->string(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['detail'])->required(),
                'dataset' => $schema->string()->enum(['reports'])->required(),
                'id' => $schema->integer()->required(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['detail'])->required(),
                'dataset' => $schema->string()->enum(['payslips'])->required(),
                ...$period,
                'worker_id' => $schema->integer()->required(),
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['summary'])->required(),
                'dataset' => $schema->string()->enum(['reports'])->required(),
                ...$period,
            ])->withoutAdditionalProperties(),
            $schema->object([
                'operation' => $schema->string()->enum(['summary'])->required(),
                'dataset' => $schema->string()->enum(['payslips'])->required(),
                ...$period,
                'worker_id' => $schema->integer(),
            ])->withoutAdditionalProperties(),
        ])->required()];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array { $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        $dataset = $this->dataset($request);
        if ($operation === 'summary') { [$store, $year, $month] = $this->period($request);
            $report = Resolver::resolve(PayrollReportReadService::class)->build($this->actor->resolveScopeUser(), $store, $year, $month);
            $workerId = Typer::parseNullableInt($request['worker_id'] ?? null);
            if ($dataset === 'payslips' && $workerId !== null) { $detail = Resolver::resolve(PayrollReportReadService::class)->buildDetail($this->actor->resolveScopeUser(), $store, $year, $month, $workerId);

                return $this->summaryResult($request, 'payslips', $detail ?? [], $detail === null ? 'NO_PAYSLIP' : null); } $payslips = $this->rows($report['payslips'] ?? []);
            $totals = ['worker_count' => \count($payslips), 'planned_minutes' => \array_sum(\array_map(static fn(array $row): int => Typer::parseInt($row['planned_minutes'] ?? null), $payslips)), 'actual_seconds' => \array_sum(\array_map(static fn(array $row): int => Typer::parseInt($row['actual_seconds'] ?? null), $payslips)), 'final_amount' => \round(\array_sum(\array_map(static fn(array $row): float => Typer::parseFloat($row['final_amount'] ?? null), $payslips)), 2), 'incomplete_count' => \array_sum(\array_map(static fn(array $row): int => Typer::parseInt($row['incomplete_count'] ?? null), $payslips))];
            if ($dataset === 'payslips') { return $this->summaryResult($request, 'payslips', ['totals' => $totals], $payslips === [] ? 'NO_PAYROLL_ACTIVITY' : null); }
            if ($dataset !== 'reports') { throw new InvalidArgumentException('Unknown payroll dataset.'); }
            $report['totals'] = $totals;

            return $this->summaryResult($request, 'reports', $report, $payslips === [] ? 'NO_PAYROLL_ACTIVITY' : null); } if ($operation === 'detail' && $dataset === 'payslips') { [$store, $year, $month] = $this->period($request);
                $workerId = Typer::parseNullableInt($request['worker_id'] ?? null);
                if ($workerId === null) { throw new InvalidArgumentException('A worker is required for payslip detail.'); }
                $detail = Resolver::resolve(PayrollReportReadService::class)->buildDetail($this->actor->resolveScopeUser(), $store, $year, $month, $workerId);

                return $this->detailResult($request, 'payslips', $detail ?? ['worker_id' => $workerId, 'available' => false]); } if ($operation === 'detail') { $reportModel = $this->report(Typer::parseNullableInt($request['id'] ?? null));
                    $store = $this->ownedStore($reportModel->getStoreId(), true);

                    return $this->detailResult($request, 'reports', [...$this->record($reportModel, $store), 'data' => Resolver::resolve(PayrollReportReadService::class)->build($this->actor->resolveScopeUser(), $store, $reportModel->getYear(), $reportModel->getMonth())]); } if ($operation !== 'list') { throw new InvalidArgumentException('Unknown payroll read operation.'); } if ($dataset === 'payslips') { [$store, $year, $month] = $this->period($request);
                        $rows = $this->rows(Resolver::resolve(PayrollReportReadService::class)->build($this->actor->resolveScopeUser(), $store, $year, $month)['payslips'] ?? []);
                        $workerId = Typer::parseNullableInt($request['worker_id'] ?? null);
                        if ($workerId !== null) { $rows = \array_values(\array_filter($rows, static fn(array $row): bool => $workerId === Typer::parseInt($row['worker_id'] ?? null))); } $state = $this->cursorState($request, 'payslips', $request);
                        $snapshot = $this->rowsSnapshot($rows);
                        if ($this->snapshotChanged($state, $snapshot)) {
                            return $this->dataChangedResult($request, 'payslips', $state['as_of']);
                        }
                        $after = Typer::parseNullableInt($state['after']['worker_id'] ?? null);
                        $rows = \array_values(\array_filter($rows, static fn(array $row): bool => $after === null || $after < Typer::parseInt($row['worker_id'] ?? null)));
                        $limit = $this->limit($request);
                        $hasMore = $limit < \count($rows);
                        $rows = \array_slice($rows, 0, $limit);
                        $last = $rows === [] ? $after : Typer::parseInt($rows[\array_key_last($rows)]['worker_id'] ?? null);

                        return $this->listResult($request, 'payslips', $rows, $request, $hasMore, ['worker_id' => $last, 'snapshot' => $snapshot]); } $query = PayrollReport::query();
        PayrollReport::scopeForUser($query, $this->actor->resolveScopeUser());
        foreach (['store_id', 'year', 'month'] as $column) { $value = Typer::parseNullableInt($request[$column] ?? null);
            if ($value !== null) { if ($column === 'store_id') { $this->ownedStore($value, true); } $query->where($column, $value); } } $status = Typer::parseNullableString($request['status'] ?? null);
        if ($status !== null) { $query->where('status', $status); }

        return $this->paginateById($query, $request, 'reports', $request, fn(PayrollReport $report): array => $this->record($report, $this->ownedStore($report->getStoreId(), true))); }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'payroll'; }

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string { return Typer::parseNullableString($request['dataset'] ?? null) ?? 'reports'; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array{Store, int, int}
     */
    private function period(array $request): array { $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        $year = Typer::parseNullableInt($request['year'] ?? null);
        $month = Typer::parseNullableInt($request['month'] ?? null);
        if ($storeId === null || $year === null || $month === null) { throw new InvalidArgumentException('Store, year, and month are required for payroll data.'); }

        return [$this->ownedStore($storeId, true), $year, $month]; }

    /**
     * Resolve one tenant-scoped payroll report.
     */
    private function report(int|null $id): PayrollReport { if ($id === null) { throw new InvalidArgumentException('A payroll report identifier is required.'); } $query = PayrollReport::query();
        PayrollReport::scopeForUser($query, $this->actor->resolveScopeUser());

        return $query->findOrFail($id); }

    /**
     * @return array<string, mixed>
     */
    private function record(PayrollReport $report, Store $store): array { return ['id' => $report->getKey(), 'store_id' => $report->getStoreId(), 'store_name' => $store->getName(), 'year' => $report->getYear(), 'month' => $report->getMonth(), 'status' => $report->getStatus()->value, 'closed_at' => $report->getClosedAt()?->toJSON(), 'reopened_at' => $report->getReopenedAt()?->toJSON(), 'url' => Resolver::resolveUrlGenerator()->route('payroll.index', ['store_id' => $report->getStoreId(), 'year' => $report->getYear(), 'month' => $report->getMonth()])]; }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $value): array
    {
        $rows = [];
        foreach (Typer::assertArray($value) as $row) {
            $rows[] = Typer::assertStringKeyArray(Typer::assertArray($row));
        }

        return $rows;
    }
}
