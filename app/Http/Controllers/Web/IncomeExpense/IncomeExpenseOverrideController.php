<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\IncomeExpense;

use App\Enums\FinancialSourceTypeEnum;
use App\Http\Controllers\Web\Concerns\ResolvesFinancialReportContext;
use App\Http\Validation\FinancialReportValidity;
use App\Models\User;
use App\Services\FinancialReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class IncomeExpenseOverrideController
{
    use ResolvesFinancialReportContext;

    /**
     * Store or replace an automatic-row override.
     */
    public function store(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->financialStore($request, $admin);
        $validity = FinancialReportValidity::inject();
        $validated = $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'source_type' => $validity->sourceType()->required()->toArray(),
            'source_key' => $validity->sourceKey()->required()->toArray(),
            'amount' => $validity->amount()->required()->toArray(),
        ]);
        (new FinancialReportService())->setOverride(
            $admin,
            $store,
            $validated->parseInt('year'),
            $validated->parseInt('month'),
            FinancialSourceTypeEnum::from($validated->assertString('source_type')),
            $validated->assertString('source_key'),
            $validated->parseFloat('amount'),
        );
        Inertia::flash('success', \__('Financial override saved.'));

        return $this->redirect($validated->parseInt('year'), $validated->parseInt('month'));
    }

    /**
     * Remove an automatic-row override.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->financialStore($request, $admin);
        $validity = FinancialReportValidity::inject();
        $validated = $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'source_type' => $validity->sourceType()->required()->toArray(),
            'source_key' => $validity->sourceKey()->required()->toArray(),
        ]);
        (new FinancialReportService())->resetOverride(
            $admin,
            $store,
            $validated->parseInt('year'),
            $validated->parseInt('month'),
            FinancialSourceTypeEnum::from($validated->assertString('source_type')),
            $validated->assertString('source_key'),
        );
        Inertia::flash('success', \__('Financial override removed.'));

        return $this->redirect($validated->parseInt('year'), $validated->parseInt('month'));
    }

    /**
     * Redirect back to the selected report month.
     */
    private function redirect(int $year, int $month): RedirectResponse
    {
        return Resolver::resolveRedirector()->route('income-expenses.index', ['year' => $year, 'month' => $month]);
    }
}
