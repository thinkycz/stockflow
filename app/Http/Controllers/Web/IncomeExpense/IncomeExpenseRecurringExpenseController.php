<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\IncomeExpense;

use App\Http\Controllers\Web\Concerns\ResolvesFinancialReportContext;
use App\Http\Validation\FinancialReportValidity;
use App\Models\Store;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class IncomeExpenseRecurringExpenseController
{
    use ResolvesFinancialReportContext;

    /**
     * Render recurring-expense management for the active store.
     */
    public function index(Request $request): Response
    {
        $admin = User::mustAuth();
        $now = Carbon::now();
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            $year = $now->year;
            $month = $now->month;
        }
        $store = ActiveStoreResolver::resolve($request, $admin);

        return Inertia::render('income-expenses/RecurringExpenses', [
            'active_store' => $store instanceof Store ? [
                'id' => $store->getKey(),
                'name' => $store->getName(),
                'is_warehouse' => $store->isWarehouse(),
            ] : null,
            'filters' => ['year' => $year, 'month' => $month],
            'recurring_expenses' => $store instanceof Store && !$store->isWarehouse()
                ? (new FinancialReportService())->recurringExpenses($admin, $store, $year, $month)
                : [],
        ]);
    }

    /**
     * Create a recurring expense.
     */
    public function store(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->financialStore($request, $admin);
        $payload = $this->expensePayload($request);
        $effectiveFrom = new CarbonImmutable($payload->assertString('effective_period') . '-01');
        (new FinancialReportService())->createRecurringExpense(
            $admin,
            $store,
            $effectiveFrom->year,
            $effectiveFrom->month,
            $payload->assertString('label'),
            $payload->parseFloat('amount'),
            $payload->parseInt('due_day'),
            $payload->assertNullableString('note'),
        );
        Inertia::flash('success', \__('Recurring expense created.'));

        return $this->redirect($payload);
    }

    /**
     * Add or replace an effective recurring-expense version.
     */
    public function update(Request $request, int $recurringExpense): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->financialStore($request, $admin);
        $payload = $this->expensePayload($request);
        $effectiveFrom = new CarbonImmutable($payload->assertString('effective_period') . '-01');
        (new FinancialReportService())->changeRecurringExpense(
            $admin,
            $store,
            $recurringExpense,
            $effectiveFrom->year,
            $effectiveFrom->month,
            $payload->assertString('label'),
            $payload->parseFloat('amount'),
            $payload->parseInt('due_day'),
            $payload->assertNullableString('note'),
        );
        Inertia::flash('success', \__('Recurring expense change saved.'));

        return $this->redirect($payload);
    }

    /**
     * End a recurring expense before a selected month.
     */
    public function terminate(Request $request, int $recurringExpense): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->financialStore($request, $admin);
        $payload = $this->terminationPayload($request);
        $endsBefore = new CarbonImmutable($payload->assertString('ends_before_period') . '-01');
        (new FinancialReportService())->terminateRecurringExpense(
            $admin,
            $store,
            $recurringExpense,
            $endsBefore->year,
            $endsBefore->month,
        );
        Inertia::flash('success', \__('Recurring expense ended.'));

        return $this->redirect($payload);
    }

    /**
     * Validate recurring-expense fields and page context.
     */
    private function expensePayload(Request $request): Parser
    {
        $validity = FinancialReportValidity::inject();

        return $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'effective_period' => $validity->period()->required()->toArray(),
            'label' => $validity->label()->required()->toArray(),
            'amount' => $validity->amount()->required()->toArray(),
            'due_day' => $validity->dueDay()->required()->toArray(),
            'note' => $validity->note()->nullable()->toArray(),
        ]);
    }

    /**
     * Validate termination fields and page context.
     */
    private function terminationPayload(Request $request): Parser
    {
        $validity = FinancialReportValidity::inject();

        return $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'ends_before_period' => $validity->period()->required()->toArray(),
        ]);
    }

    /**
     * Redirect back to recurring-expense management.
     */
    private function redirect(Parser $payload): RedirectResponse
    {
        return Resolver::resolveRedirector()->route('income-expenses.recurring-expenses.index', [
            'year' => $payload->parseInt('year'),
            'month' => $payload->parseInt('month'),
        ]);
    }
}
