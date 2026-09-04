<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Domain\Workforce\AttendanceReportService;
use App\Domain\Workforce\AttendanceService;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class AttendancePrintController
{
    /**
     * Render the printable attendance report.
     */
    public function __invoke(Request $request): Response
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolveIncludingInactive($request, $admin);
        if (!$store instanceof Store || $store->isWarehouse()) { \abort(404); }
        $value = $request->query('month');
        $month = \is_string($value) && \preg_match('/^\\d{4}-\\d{2}$/', $value) === 1
            ? $value : CarbonImmutable::now(AttendanceService::BUSINESS_TIMEZONE)->format('Y-m');

        return Inertia::render('attendance/Print', [
            'store' => ['id' => $store->getKey(), 'name' => $store->getName()],
            'report' => (new AttendanceReportService())->build($admin, $store, $month, Typer::parseNullableInt($request->query('worker_id'))),
        ]);
    }
}
