<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\IncomeExpense;

use App\Enums\FinancialDirectionEnum;
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
use Thinkycz\LaravelCore\Support\Thrower;

class IncomeExpenseManualRowController
{
    use ResolvesFinancialReportContext;

    /**
     * Create a manual row.
     */
    public function store(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->financialStore($request, $admin);
        $validated = $this->manualPayload($request);
        $this->assertDateInMonth($validated);
        (new FinancialReportService())->createManualRow(
            $admin,
            $store,
            $validated->parseInt('year'),
            $validated->parseInt('month'),
            FinancialDirectionEnum::from($validated->assertString('direction')),
            $validated->assertString('label'),
            $validated->assertString('occurred_on'),
            $validated->parseFloat('amount'),
            $validated->assertNullableString('note'),
        );
        Inertia::flash('success', \__('Manual financial row created.'));

        return $this->redirect($validated);
    }

    /**
     * Update a manual row.
     */
    public function update(Request $request, int $manualRow): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->financialStore($request, $admin);
        $validated = $this->manualPayload($request);
        $this->assertDateInMonth($validated);
        (new FinancialReportService())->updateManualRow(
            $admin,
            $store,
            $validated->parseInt('year'),
            $validated->parseInt('month'),
            $manualRow,
            FinancialDirectionEnum::from($validated->assertString('direction')),
            $validated->assertString('label'),
            $validated->assertString('occurred_on'),
            $validated->parseFloat('amount'),
            $validated->assertNullableString('note'),
        );
        Inertia::flash('success', \__('Manual financial row saved.'));

        return $this->redirect($validated);
    }

    /**
     * Delete a manual row.
     */
    public function destroy(Request $request, int $manualRow): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->financialStore($request, $admin);
        $period = $this->financialPeriod($request);
        (new FinancialReportService())->deleteManualRow($admin, $store, $period->parseInt('year'), $period->parseInt('month'), $manualRow);
        Inertia::flash('success', \__('Manual financial row deleted.'));

        return $this->redirect($period);
    }

    /**
     * Validate a manual-row payload.
     */
    private function manualPayload(Request $request): Parser
    {
        $validity = FinancialReportValidity::inject();

        return $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'direction' => $validity->direction()->required()->toArray(),
            'label' => $validity->label()->required()->toArray(),
            'occurred_on' => $validity->occurredOn()->required()->toArray(),
            'amount' => $validity->amount()->required()->toArray(),
            'note' => $validity->note()->nullable()->toArray(),
        ]);
    }

    /**
     * Ensure the row date belongs to the selected report month.
     */
    private function assertDateInMonth(Parser $validated): void
    {
        $date = CarbonImmutable::parse($validated->assertString('occurred_on'));
        if ($date->year !== $validated->parseInt('year') || $date->month !== $validated->parseInt('month')) {
            Thrower::default()->message('occurred_on', \__('The row date must be inside the selected month.'))->throw();
        }
    }

    /**
     * Redirect back to the selected report month.
     */
    private function redirect(Parser $validated): RedirectResponse
    {
        return Resolver::resolveRedirector()->route('income-expenses.index', ['year' => $validated->parseInt('year'), 'month' => $validated->parseInt('month')]);
    }
}
