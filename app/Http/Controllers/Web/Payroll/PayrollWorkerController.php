<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Payroll;

use App\Domain\Payroll\PayrollReportService;
use App\Http\Controllers\Web\Concerns\ResolvesPayrollReportContext;
use App\Http\Validation\PayrollReportValidity;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class PayrollWorkerController
{
    use ResolvesPayrollReportContext;

    /**
     * Add an existing worker to an open payroll report.
     */
    public function store(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->payrollStore($request, $admin);
        $validity = PayrollReportValidity::inject();
        $payload = $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'worker_id' => $validity->workerId()->required()->toArray(),
        ]);
        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $admin);
        Worker::scopeActive($workerQuery);
        $worker = $workerQuery->whereKey($payload->parseInt('worker_id'))->firstOrFail();
        (new PayrollReportService())->addWorker(
            $admin,
            $store,
            $payload->parseInt('year'),
            $payload->parseInt('month'),
            $worker,
        );
        Inertia::flash('success', \__('Payroll worker added.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Remove an empty manually added worker from an open payroll report.
     */
    public function destroy(Request $request, int $worker): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->payrollStore($request, $admin);
        $period = $this->payrollPeriod($request);
        (new PayrollReportService())->removeWorker(
            $admin,
            $store,
            $period->parseInt('year'),
            $period->parseInt('month'),
            $worker,
        );
        Inertia::flash('success', \__('Payroll worker removed.'));

        return Resolver::resolveRedirector()->route('payroll.index', [
            'store_id' => $store->getKey(),
            'year' => $period->parseInt('year'),
            'month' => $period->parseInt('month'),
        ]);
    }
}
