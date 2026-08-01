<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Payroll;

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

class PayrollWageOverrideController
{
    use ResolvesPayrollReportContext;

    /**
     * Save a worker's monthly hours and hourly rate.
     */
    public function update(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->payrollStore($request, $admin);
        $payload = $this->overridePayload($request);
        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $admin);
        $worker = $workerQuery->whereKey($payload->parseInt('worker_id'))->firstOrFail();
        (new PayrollReportService())->upsertWageOverride(
            $admin,
            $store,
            $payload->parseInt('year'),
            $payload->parseInt('month'),
            $worker,
            $payload->parseFloat('hours'),
            $payload->parseFloat('hourly_rate'),
        );
        Inertia::flash('success', \__('Payroll wage override saved.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Restore automatic wages for one worker and month.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = $this->payrollStore($request, $admin);
        $payload = $this->resetPayload($request);
        (new PayrollReportService())->deleteWageOverride(
            $admin,
            $store,
            $payload->parseInt('year'),
            $payload->parseInt('month'),
            $payload->parseInt('worker_id'),
        );
        Inertia::flash('success', \__('Automatic payroll calculation restored.'));

        return Resolver::resolveRedirector()->back();
    }

    /**
     * Validate an override payload.
     */
    private function overridePayload(Request $request): Parser
    {
        $validity = PayrollReportValidity::inject();

        return $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'worker_id' => $validity->workerId()->required()->toArray(),
            'hours' => $validity->hours()->required()->toArray(),
            'hourly_rate' => $validity->hourlyRate()->required()->toArray(),
        ]);
    }

    /**
     * Validate the reset payload.
     */
    private function resetPayload(Request $request): Parser
    {
        $validity = PayrollReportValidity::inject();

        return $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
            'worker_id' => $validity->workerId()->required()->toArray(),
        ]);
    }
}
