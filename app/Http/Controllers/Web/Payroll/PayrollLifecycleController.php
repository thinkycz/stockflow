<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Web\Concerns\ResolvesPayrollReportContext;
use App\Models\User;
use App\Services\PayrollReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;

class PayrollLifecycleController
{
    use ResolvesPayrollReportContext;

    /**
     * Close and freeze the selected payroll month.
     */
    public function close(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->payrollStore($request, $admin);
        $period = $this->payrollPeriod($request);
        (new PayrollReportService())->close(
            $admin,
            $store,
            $period->parseInt('year'),
            $period->parseInt('month'),
        );
        Inertia::flash('success', \__('Payroll report closed.'));

        return $this->redirect($period);
    }

    /**
     * Reopen the selected payroll month.
     */
    public function reopen(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->payrollStore($request, $admin);
        $period = $this->payrollPeriod($request);
        (new PayrollReportService())->reopen(
            $admin,
            $store,
            $period->parseInt('year'),
            $period->parseInt('month'),
        );
        Inertia::flash('success', \__('Payroll report reopened.'));

        return $this->redirect($period);
    }

    /**
     * Redirect to the selected payroll month.
     */
    private function redirect(Parser $period): RedirectResponse
    {
        return Resolver::resolveRedirector()->route('payroll.index', [
            'year' => $period->parseInt('year'),
            'month' => $period->parseInt('month'),
        ]);
    }
}
