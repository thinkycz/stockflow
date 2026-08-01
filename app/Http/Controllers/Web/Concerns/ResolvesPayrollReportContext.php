<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Concerns;

use App\Http\Validation\PayrollReportValidity;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Parser;

trait ResolvesPayrollReportContext
{
    use ValidatesWebRequests;

    /**
     * Resolve the active retail store or fail.
     */
    private function payrollStore(Request $request, User $admin): Store
    {
        $store = ActiveStoreResolver::resolve($request, $admin);
        if (!$store instanceof Store || $store->isWarehouse()) {
            \abort(404);
        }

        return $store;
    }

    /**
     * Validate the shared report period.
     */
    private function payrollPeriod(Request $request): Parser
    {
        $validity = PayrollReportValidity::inject();

        return $this->validateRequest($request, [
            'year' => $validity->year()->required()->toArray(),
            'month' => $validity->month()->required()->toArray(),
        ]);
    }
}
