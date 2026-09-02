<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Web\Concerns\ResolvesPayrollReportContext;
use App\Http\Validation\PayrollReportValidity;
use App\Models\User;
use App\Services\PayrollReportService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class PayrollTipDistributionController
{
    use ResolvesPayrollReportContext;

    /**
     * Distribute one tip amount across payable workers.
     */
    public function store(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->payrollStore($request, $admin);
        $validity = PayrollReportValidity::inject();
        $payload = $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'amount' => $validity->amount()->required()->toArray(),
        ]);
        (new PayrollReportService())->distributeTips(
            $admin,
            $store,
            $payload->parseInt('year'),
            $payload->parseInt('month'),
            Money::input($request->input('amount')),
        );
        Inertia::flash('success', \__('Tips distributed proportionally.'));

        return Resolver::resolveRedirector()->back();
    }
}
