<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\FinancialRecurringExpense;
use App\Models\FinancialRecurringExpenseVersion;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

final class ReadRecurringExpensesTool extends AbstractReadResourceTool
{
    /**
     * Stable provider-facing tool name.
     */
    public function name(): string { return 'read_recurring_expenses'; }

    /**
     * Explain the recurring-expense datasets available to the model.
     */
    public function description(): string { return 'Read recurring expenses with full version history, effective periods, labels, amounts, due days, notes, lifecycle status, and exact effective totals.'; }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array { $filters = ['dataset' => $schema->string()->enum(['expenses', 'versions'])->required(), 'search' => $schema->string(), 'store_id' => $schema->integer(), 'year' => $schema->integer()->min(2000)->max(2100), 'month' => $schema->integer()->min(1)->max(12), 'status' => $schema->string()->enum(['active', 'ended']), 'expense_id' => $schema->integer()];

        return ['request' => $schema->anyOf([$schema->object(['operation' => $schema->string()->enum(['list'])->required(), ...$filters, 'limit' => $schema->integer()->min(1)->max(50), 'cursor' => $schema->string()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['detail'])->required(), 'dataset' => $schema->string()->enum(['expenses', 'versions'])->required(), 'id' => $schema->integer()->required()])->withoutAdditionalProperties(), $schema->object(['operation' => $schema->string()->enum(['summary'])->required(), ...$filters])->withoutAdditionalProperties()])->required()]; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function execute(array $request): array { $operation = Typer::parseNullableString($request['operation'] ?? null) ?? 'list';
        if ($this->dataset($request) === 'versions') { return $this->versions($request, $operation); }
        if ($this->dataset($request) !== 'expenses') { throw new InvalidArgumentException('Unknown recurring expense dataset.'); }
        $query = FinancialRecurringExpense::query()->with('versions');
        FinancialRecurringExpense::scopeForUser($query, $this->actor->resolveScopeUser());
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) { $this->ownedStore($storeId, true);
            $query->where('store_id', $storeId); } $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') { FinancialRecurringExpense::scopeSearch($query, \mb_trim($search)); } $period = $this->period($request);
        if ($period !== null) { $query->where('starts_on', '<=', $period . '-01')->where(static fn($end) => $end->whereNull('ends_before')->orWhere('ends_before', '>', $period . '-01')); } $status = Typer::parseNullableString($request['status'] ?? null);
        if ($status === 'active') { $query->whereNull('ends_before'); } if ($status === 'ended') { $query->whereNotNull('ends_before'); } if ($operation === 'detail') { $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('A recurring expense identifier is required.'); }

            return $this->detailResult($request, 'expenses', $this->record($query->findOrFail($id), true, $period)); } if ($operation === 'summary') { $expenses = $query->get();
                $records = $expenses->map(fn(FinancialRecurringExpense $expense): array => $this->record($expense, false, $period));

                return $this->summaryResult($request, 'expenses', ['expense_count' => $expenses->count(), 'effective_total' => \round($records->sum(static fn(array $row): float => Typer::parseFloat($row['amount'] ?? 0)), 2), 'expenses' => $records->values()->all()], $expenses->isEmpty() ? 'NO_MATCHING_DATA' : null); } if ($operation !== 'list') { throw new InvalidArgumentException('Unknown recurring expense read operation.'); }

        return $this->paginateById($query, $request, 'expenses', $request, fn(FinancialRecurringExpense $expense): array => $this->record($expense, false, $period)); }

    /**
     * Resource identifier used by cursors, envelopes, and audits.
     */
    protected function resource(): string { return 'recurring_expenses'; }

    /**
     * @param array<string, mixed> $request
     */
    protected function dataset(array $request): string { return Typer::parseNullableString($request['dataset'] ?? null) ?? 'expenses'; }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    private function versions(array $request, string $operation): array
    {
        $query = FinancialRecurringExpenseVersion::query()->with('recurringExpense');
        $ownerId = $this->actor->resolveScopeUser()->getKey();
        $storeId = Typer::parseNullableInt($request['store_id'] ?? null);
        if ($storeId !== null) { $this->ownedStore($storeId, true); }
        $status = Typer::parseNullableString($request['status'] ?? null);
        $query->whereHas('recurringExpense', static function (Builder $expenses) use ($ownerId, $storeId, $status): void {
            $expenses->where('user_id', $ownerId);
            if ($storeId !== null) { $expenses->where('store_id', $storeId); }
            if ($status === 'active') { $expenses->whereNull('ends_before'); }
            if ($status === 'ended') { $expenses->whereNotNull('ends_before'); }
        });
        $expenseId = Typer::parseNullableInt($request['expense_id'] ?? null);
        if ($expenseId !== null) { $query->where('financial_recurring_expense_id', $expenseId); }
        $search = Typer::parseNullableString($request['search'] ?? null);
        if ($search !== null && \mb_trim($search) !== '') { FinancialRecurringExpenseVersion::scopeSearch($query, \mb_trim($search)); }
        $year = Typer::parseNullableInt($request['year'] ?? null);
        if ($year !== null) { $query->whereYear('effective_from', $year); }
        $month = Typer::parseNullableInt($request['month'] ?? null);
        if ($month !== null) { $query->whereMonth('effective_from', $month); }

        if ($operation === 'detail') { $id = Typer::parseNullableInt($request['id'] ?? null);
            if ($id === null) { throw new InvalidArgumentException('A recurring expense version identifier is required.'); }

            return $this->detailResult($request, 'versions', $this->versionRecord($query->findOrFail($id))); }
        if ($operation === 'summary') { $count = (clone $query)->count();

            return $this->summaryResult($request, 'versions', ['version_count' => $count, 'amount_total' => \round(Typer::parseFloat((clone $query)->sum('amount')), 2)], $count === 0 ? 'NO_MATCHING_DATA' : null); }
        if ($operation !== 'list') { throw new InvalidArgumentException('Unknown recurring expense version operation.'); }

        return $this->paginateById($query, $request, 'versions', $request, fn(FinancialRecurringExpenseVersion $version): array => $this->versionRecord($version));
    }

    /**
     * @param array<string, mixed> $request
     */
    private function period(array $request): string|null { $year = Typer::parseNullableInt($request['year'] ?? null);
        $month = Typer::parseNullableInt($request['month'] ?? null);

        return $year !== null && $month !== null ? \sprintf('%04d-%02d', $year, $month) : null; }

    /**
     * @return array<string, mixed>
     */
    private function record(FinancialRecurringExpense $expense, bool $versions, string|null $period): array { $effective = $expense->getVersions()->filter(static fn(FinancialRecurringExpenseVersion $version): bool => $period === null || $period >= \mb_substr($version->getEffectiveFrom(), 0, 7))->sortBy(static fn(FinancialRecurringExpenseVersion $version): string => $version->getEffectiveFrom())->last();
        $record = ['id' => $expense->getKey(), 'store_id' => $expense->getStoreId(), 'starts_on' => $expense->getStartsOn(), 'ends_before' => $expense->getEndsBefore(), 'label' => $effective?->getLabel(), 'amount' => $effective?->getAmount(), 'due_day' => $effective?->getDueDay(), 'note' => $effective?->getNote(), 'effective_from' => $effective?->getEffectiveFrom(), 'url' => Resolver::resolveUrlGenerator()->route('income-expenses.recurring-expenses.index', ['store_id' => $expense->getStoreId()])];
        if ($versions) { $record['versions'] = $expense->getVersions()->map(static fn(FinancialRecurringExpenseVersion $version): array => ['id' => $version->getKey(), 'effective_from' => $version->getEffectiveFrom(), 'label' => $version->getLabel(), 'amount' => $version->getAmount(), 'due_day' => $version->getDueDay(), 'note' => $version->getNote()])->values()->all(); }

        return $record; }

    /**
     * @return array<string, mixed>
     */
    private function versionRecord(FinancialRecurringExpenseVersion $version): array
    {
        $expense = $version->getRecurringExpense();

        return [
            'id' => $version->getKey(),
            'expense_id' => $version->getFinancialRecurringExpenseId(),
            'store_id' => $expense->getStoreId(),
            'effective_from' => $version->getEffectiveFrom(),
            'label' => $version->getLabel(),
            'amount' => $version->getAmount(),
            'due_day' => $version->getDueDay(),
            'note' => $version->getNote(),
            'expense_starts_on' => $expense->getStartsOn(),
            'expense_ends_before' => $expense->getEndsBefore(),
            'url' => Resolver::resolveUrlGenerator()->route('income-expenses.recurring-expenses.index', ['store_id' => $expense->getStoreId()]),
        ];
    }
}
