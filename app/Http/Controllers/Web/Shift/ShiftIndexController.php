<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Domain\Workforce\ShiftOverviewService;
use App\Domain\Workforce\ShiftRequestService;
use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\ShiftRequest;
use App\Models\ShiftShareLink;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Support\ActiveStoreResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftIndexController
{
    /**
     * Page size hint required by the web index controller architecture test.
     * Shifts render within a single month calendar, so the list is always
     * bounded by the calendar and pagination is not exposed.
     */
    public const int TAKE = 1000;

    /**
     * Render the shifts calendar for the active store and month.
     */
    public function __invoke(Request $request): Response
    {
        $user = User::mustAuth();
        $scopeUser = $user->resolveScopeUser();
        $store = ActiveStoreResolver::resolve($request, $user);

        $now = \Illuminate\Support\Carbon::now();
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;

        $shifts = [];
        $attendanceRatings = [];
        /** @var Collection<int, Shift> $shiftModels */
        $shiftModels = new Collection();

        if ($store instanceof Store) {
            $query = Shift::query();
            Shift::scopeForUser($query, $scopeUser);
            Shift::scopeForStore($query, $store->getKey());
            Shift::scopeForMonth($query, $year, $month);
            Shift::querySelect($query);
            $query->orderBy('date')->orderBy('start_time');

            $shiftModels = $query->take(self::TAKE)->get();
            foreach ($shiftModels as $shift) {
                $shifts[] = [
                    'id' => $shift->getKey(),
                    'worker_id' => $shift->getWorkerId(),
                    'date' => $shift->getDate(),
                    'start_time' => $shift->getStartTimeShort(),
                    'end_time' => $shift->getEndTimeShort(),
                ];
            }
        }

        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $scopeUser);
        Worker::querySelect($workerQuery);
        $workerModels = $workerQuery->orderBy('last_name')->orderBy('first_name')->get();
        $overview = $store instanceof Store
            ? (new ShiftOverviewService())->build($scopeUser, $store, $shiftModels, $workerModels, $user->isAdmin())
            : ['ratings' => [], 'monthly_summary' => []];
        $attendanceRatings = $overview['ratings'];
        foreach ($shifts as &$shift) {
            $shift['attendance_rating'] = $attendanceRatings[$shift['id']];
        }
        unset($shift);

        $workers = $workerModels->map(
            static function (Worker $worker): array {
                return [
                    'id' => $worker->getKey(),
                    'first_name' => $worker->getFirstName(),
                    'last_name' => $worker->getLastName(),
                    'color' => $worker->getCalendarColor(),
                    'attendance_rating_enabled' => $worker->isAttendanceRatingEnabled(),
                    'archived' => $worker->isArchived(),
                ];
            },
        )->all();

        $props = [
            'store' => $store instanceof Store ? [
                'id' => $store->getKey(),
                'name' => $store->getName(),
            ] : null,
            'shifts' => $shifts,
            'workers' => $workers,
            'filters' => [
                'store_id' => $store?->getKey(),
                'year' => $year,
                'month' => $month,
            ],
            'is_admin' => $user->isAdmin(),
            'monthly_summary' => $overview['monthly_summary'],
        ];

        if ($user->isAdmin()) {
            $shareLinkQuery = ShiftShareLink::query();
            ShiftShareLink::scopeForUser($shareLinkQuery, $scopeUser);

            if ($store instanceof Store) {
                ShiftShareLink::scopeForStore($shareLinkQuery, $store->getKey());
            } else {
                $shareLinkQuery->whereRaw('1 = 0');
            }

            ShiftShareLink::querySelect($shareLinkQuery);
            $props['shift_share_links'] = $shareLinkQuery
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(static fn(ShiftShareLink $link): array => [
                    'id' => $link->getKey(),
                    'name' => $link->getName() ?? \__('Original public link'),
                    'url' => Resolver::resolveUrlGenerator()->to('public/shifts/' . $link->getToken()),
                    'created_at' => $link->getCreatedAt()->toIso8601String(),
                ])
                ->all();

            $shiftRequests = [];
            if ($store instanceof Store) {
                $shiftRequestQuery = ShiftRequest::query();
                ShiftRequest::scopeForUser($shiftRequestQuery, $scopeUser);
                ShiftRequest::scopeForStore($shiftRequestQuery, $store->getKey());
                ShiftRequest::scopeForMonth($shiftRequestQuery, $year, $month);
                ShiftRequest::querySelect($shiftRequestQuery);
                $shiftRequests = $shiftRequestQuery->orderBy('date')->orderBy('start_time')->take(self::TAKE)->get()
                    ->map(static fn(ShiftRequest $shiftRequest): array => [
                        'id' => $shiftRequest->getKey(),
                        'worker_id' => $shiftRequest->getWorkerId(),
                        'date' => $shiftRequest->getDate(),
                        'start_time' => $shiftRequest->getStartTimeShort(),
                        'end_time' => $shiftRequest->getEndTimeShort(),
                    ])->all();
            }
            $shiftRequestService = new ShiftRequestService();
            $props['shift_requests'] = $shiftRequests;
            $props['request_month_locked'] = $store instanceof Store && $shiftRequestService->isLocked($store, $year, $month);
            $props['request_month_is_future'] = $shiftRequestService->isFuturePeriod($year, $month);

            $presetQuery = ShiftPreset::query();
            ShiftPreset::scopeForUser($presetQuery, $scopeUser);

            if ($store instanceof Store) {
                ShiftPreset::scopeForStore($presetQuery, $store->getKey());
            } else {
                $presetQuery->whereRaw('1 = 0');
            }

            ShiftPreset::querySelect($presetQuery);
            $props['shift_presets'] = $presetQuery
                ->orderBy('start_time')
                ->orderBy('name')
                ->get()
                ->map(static fn(ShiftPreset $preset): array => [
                    'id' => $preset->getKey(),
                    'name' => $preset->getName(),
                    'start_time' => $preset->getStartTimeShort(),
                    'end_time' => $preset->getEndTimeShort(),
                ])
                ->all();
        }

        return Inertia::render('shifts/Index', $props);
    }
}
