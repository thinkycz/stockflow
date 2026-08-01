<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Models\Store;
use App\Models\User;
use App\Services\AttendanceOverviewService;
use App\Services\AttendanceReportService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceIndexController
{
    public const int TAKE = 1000;

    /**
     * Render attendance controls and the current store state.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $owner = $user->resolveScopeUser();
        $store = ActiveStoreResolver::resolve($request, $user);
        $props = [
            'store' => $store instanceof Store ? ['id' => $store->getKey(), 'name' => $store->getName(), 'is_warehouse' => $store->isWarehouse()] : null,
            'attendance_rows' => [], 'off_schedule_workers' => [],
            'store_state' => 'empty', 'is_admin' => $user->isAdmin(),
        ];
        if (!$store instanceof Store || $store->isWarehouse()) {
            return Inertia::render('attendance/Index', $props);
        }

        $props = [...$props, ...(new AttendanceOverviewService())->build($owner, $store)];
        $reportService = new AttendanceReportService();
        $props['store_state'] = $reportService->storeState($owner, $store);

        return Inertia::render('attendance/Index', $props);
    }
}
