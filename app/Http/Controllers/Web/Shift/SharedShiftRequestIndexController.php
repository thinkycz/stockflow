<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Models\ShiftRequest;
use App\Models\ShiftShareLink;
use App\Models\Store;
use App\Models\Worker;
use App\Services\ShiftRequestService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class SharedShiftRequestIndexController
{
    public const int TAKE = 1000;

    /**
     * Render the public shift-request calendar.
     */
    public function __invoke(Request $request, string $token): Response
    {
        $store = ShiftShareLink::findStoreForToken($token);

        if (!$store instanceof Store) {
            \abort(404);
        }

        $nextMonth = CarbonImmutable::now()->startOfMonth()->addMonth();
        $year = Typer::parseNullableInt($request->query('year')) ?? $nextMonth->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $nextMonth->month;
        $service = new ShiftRequestService();
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100 || !$service->isFuturePeriod($year, $month)) {
            $year = $nextMonth->year;
            $month = $nextMonth->month;
        }

        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $store->getUserId());
        Worker::scopeActive($workerQuery);
        Worker::querySelect($workerQuery);
        $workers = $workerQuery->orderBy('last_name')->orderBy('first_name')->get();
        $selectedWorkerId = Typer::parseNullableInt($request->query('worker_id'));
        $selectedWorker = $workers->first(
            static fn(Worker $worker): bool => $selectedWorkerId === $worker->getKey(),
        );

        $shiftRequests = [];
        if ($selectedWorker instanceof Worker) {
            $shiftRequestQuery = ShiftRequest::query();
            ShiftRequest::scopeForUser($shiftRequestQuery, $store->getUserId());
            ShiftRequest::scopeForStore($shiftRequestQuery, $store->getKey());
            ShiftRequest::scopeForWorker($shiftRequestQuery, $selectedWorker->getKey());
            ShiftRequest::scopeForMonth($shiftRequestQuery, $year, $month);
            ShiftRequest::querySelect($shiftRequestQuery);
            $shiftRequests = $shiftRequestQuery->orderBy('date')->take(self::TAKE)->get()
                ->map(static fn(ShiftRequest $shiftRequest): array => self::shiftRequestData($shiftRequest))
                ->all();
        }

        return Inertia::render('public-shift-requests/Index', [
            'store' => ['name' => $store->getName()],
            'workers' => $workers->map(static fn(Worker $worker): array => [
                'id' => $worker->getKey(),
                'name' => $worker->getFullName(),
                'color' => $worker->getCalendarColor(),
            ])->all(),
            'selected_worker_id' => $selectedWorker?->getKey(),
            'shift_requests' => $shiftRequests,
            'is_locked' => $service->isLocked($store, $year, $month),
            'filters' => ['year' => $year, 'month' => $month],
            'share_token' => $token,
        ]);
    }

    /**
     * @return array{id: int, worker_id: int, date: string, start_time: string, end_time: string}
     */
    private static function shiftRequestData(ShiftRequest $shiftRequest): array
    {
        return [
            'id' => $shiftRequest->getKey(),
            'worker_id' => $shiftRequest->getWorkerId(),
            'date' => $shiftRequest->getDate(),
            'start_time' => $shiftRequest->getStartTimeShort(),
            'end_time' => $shiftRequest->getEndTimeShort(),
        ];
    }
}
