<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Payroll;

use App\Enums\PayrollAdjustmentTypeEnum;
use App\Http\Controllers\Web\Concerns\ResolvesPayrollReportContext;
use App\Http\Validation\PayrollReportValidity;
use App\Models\User;
use App\Models\Worker;
use App\Services\PayrollReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Parser;
use Thinkycz\LaravelCore\Support\Resolver;

class PayrollAdjustmentController
{
    use ResolvesPayrollReportContext;

    /**
     * Create a payroll adjustment.
     */
    public function store(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->payrollStore($request, $admin);
        $payload = $this->adjustmentPayload($request);
        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $admin);
        $worker = $workerQuery->whereKey($payload->parseInt('worker_id'))->firstOrFail();
        (new PayrollReportService())->createAdjustment(
            $admin,
            $store,
            $payload->parseInt('year'),
            $payload->parseInt('month'),
            $worker,
            PayrollAdjustmentTypeEnum::from($payload->assertString('type')),
            $payload->parseFloat('amount'),
            $payload->assertString('reason'),
        );
        Inertia::flash('success', \__('Payroll adjustment created.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Update a payroll adjustment.
     */
    public function update(Request $request, int $payrollAdjustment): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->payrollStore($request, $admin);
        $payload = $this->adjustmentPayload($request);
        (new PayrollReportService())->updateAdjustment(
            $admin,
            $store,
            $payload->parseInt('year'),
            $payload->parseInt('month'),
            $payrollAdjustment,
            PayrollAdjustmentTypeEnum::from($payload->assertString('type')),
            $payload->parseFloat('amount'),
            $payload->assertString('reason'),
        );
        Inertia::flash('success', \__('Payroll adjustment saved.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Delete a payroll adjustment.
     */
    public function destroy(Request $request, int $payrollAdjustment): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->payrollStore($request, $admin);
        $period = $this->payrollPeriod($request);
        (new PayrollReportService())->deleteAdjustment(
            $admin,
            $store,
            $period->parseInt('year'),
            $period->parseInt('month'),
            $payrollAdjustment,
        );
        Inertia::flash('success', \__('Payroll adjustment deleted.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Validate an adjustment payload.
     */
    private function adjustmentPayload(Request $request): Parser
    {
        $validity = PayrollReportValidity::inject();

        return $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'worker_id' => $validity->workerId()->required()->toArray(),
            'type' => $validity->type()->required()->toArray(),
            'amount' => $validity->amount()->required()->toArray(),
            'reason' => $validity->reason()->required()->toArray(),
        ]);
    }
}
