<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\AttendanceReportService;
use App\Services\AttendanceService;
use App\Support\ActiveStoreResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class AttendanceReportController
{
    public const int TAKE = 1000;

    /**
     * Render the administrator attendance report and correction workspace.
     */
    public function __invoke(Request $request): Response
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);
        if (!$store instanceof Store || $store->isWarehouse()) {
            return Inertia::render('attendance/Report', ['store' => null, 'workers' => [], 'filters' => null, 'report' => null]);
        }

        $monthValue = $request->query('month');
        $month = \is_string($monthValue) && \preg_match('/^\\d{4}-\\d{2}$/', $monthValue) === 1
            ? $monthValue : CarbonImmutable::now(AttendanceService::BUSINESS_TIMEZONE)->format('Y-m');
        $workerId = Typer::parseNullableInt($request->query('worker_id'));
        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $admin);
        Worker::querySelect($workerQuery);
        $workers = $workerQuery->orderBy('last_name')->orderBy('first_name')->take(self::TAKE)->get();

        return Inertia::render('attendance/Report', [
            'store' => ['id' => $store->getKey(), 'name' => $store->getName()],
            'workers' => $workers->map(static fn(Worker $worker): array => [
                'id' => $worker->getKey(), 'first_name' => $worker->getFirstName(), 'last_name' => $worker->getLastName(),
            ])->all(),
            'filters' => ['month' => $month, 'worker_id' => $workerId],
            'report' => (new AttendanceReportService())->build($admin, $store, $month, $workerId),
        ]);
    }
}
