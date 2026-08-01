<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\IncomeExpense;

use App\Http\Controllers\Web\Concerns\ResolvesFinancialReportContext;
use App\Http\Validation\FinancialReportValidity;
use App\Models\User;
use App\Services\FinancialReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;

class IncomeExpenseRecurringExpenseController
{
    use ResolvesFinancialReportContext;

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
     * Redirect to the report month that opened the manager.
     */
    private function redirect(Parser $payload): RedirectResponse
    {
        return Resolver::resolveRedirector()->route('income-expenses.index', [
            'year' => $payload->parseInt('year'),
            'month' => $payload->parseInt('month'),
        ]);
    }
}
