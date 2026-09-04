<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\IncomeExpense;

use App\Domain\Finance\FinancialReportService;
use App\Http\Controllers\Web\Concerns\ResolvesFinancialReportContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;

class IncomeExpenseLifecycleController
{
    use ResolvesFinancialReportContext;

    /**
     * Copy manual rows from the previous month.
     */
    public function copyPrevious(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->financialStore($request, $admin);
        $period = $this->financialPeriod($request);
        $count = (new FinancialReportService())->copyPreviousManualRows($admin, $store, $period->parseInt('year'), $period->parseInt('month'));
        Inertia::flash('success', \__('Copied :count manual financial rows.', ['count' => $count]));

        return $this->redirect($period);
    }

    /**
     * Close and freeze the selected month.
     */
    public function close(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->financialStore($request, $admin);
        $period = $this->financialPeriod($request);
        (new FinancialReportService())->close($admin, $store, $period->parseInt('year'), $period->parseInt('month'));
        Inertia::flash('success', \__('Financial report closed.'));

        return $this->redirect($period);
    }

    /**
     * Reopen the selected month.
     */
    public function reopen(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->financialStore($request, $admin);
        $period = $this->financialPeriod($request);
        (new FinancialReportService())->reopen($admin, $store, $period->parseInt('year'), $period->parseInt('month'));
        Inertia::flash('success', \__('Financial report reopened.'));

        return $this->redirect($period);
    }

    /**
     * Redirect back to the selected report month.
     */
    private function redirect(Parser $period): RedirectResponse
    {
        return Resolver::resolveRedirector()->route('income-expenses.index', ['year' => $period->parseInt('year'), 'month' => $period->parseInt('month')]);
    }
}
