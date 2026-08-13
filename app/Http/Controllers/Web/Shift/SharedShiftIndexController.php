<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Models\Shift;
use App\Models\ShiftShareLink;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\ShiftOverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Typer;

class SharedShiftIndexController
{
    /**
     * Maximum shifts returned for one calendar month.
     */
    public const int TAKE = 1000;

    /**
     * Render a store's public read-only shift calendar.
     */
    public function __invoke(Request $request, string $token): Response
    {
        $store = ShiftShareLink::findStoreForToken($token);

        if (!$store instanceof Store) {
            \abort(404);
        }

        $now = Carbon::now();
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;

        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $store->getUserId());
        Worker::querySelect($workerQuery);

        $workerModels = $workerQuery->orderBy('last_name')->orderBy('first_name')->get();
        $workersById = [];
        foreach ($workerModels as $worker) {
            $workersById[$worker->getKey()] = [
                'name' => $worker->getFullName(),
                'color' => $worker->getCalendarColor(),
            ];
        }

        $shiftQuery = Shift::query();
        Shift::scopeForUser($shiftQuery, $store->getUserId());
        Shift::scopeForStore($shiftQuery, $store->getKey());
        Shift::scopeForMonth($shiftQuery, $year, $month);
        Shift::querySelect($shiftQuery);
        $shiftQuery->orderBy('date')->orderBy('start_time');

        $shiftModels = $shiftQuery->take(self::TAKE)->get();
        $owner = User::query()->whereKey($store->getUserId())->first();
        if (!$owner instanceof User) {
            \abort(404);
        }
        $overview = (new ShiftOverviewService())->build($owner, $store, $shiftModels, $workerModels, false);
        $shifts = $shiftModels->map(
            static function (Shift $shift) use ($workersById, $overview): array {
                $worker = $workersById[$shift->getWorkerId()] ?? null;
                $rating = $overview['ratings'][$shift->getKey()];

                return [
                    'id' => $shift->getKey(),
                    'worker_name' => $worker['name'] ?? '',
                    'worker_color' => $worker['color'] ?? '#64748B',
                    'date' => $shift->getDate(),
                    'start_time' => $shift->getStartTimeShort(),
                    'end_time' => $shift->getEndTimeShort(),
                    'attendance_rating' => [
                        'state' => $rating['state'],
                        'score' => $rating['score'],
                        'band' => $rating['band'],
                    ],
                ];
            },
        )->all();

        return Inertia::render('public-shifts/Index', [
            'store' => [
                'name' => $store->getName(),
            ],
            'shifts' => $shifts,
            'monthly_summary' => $overview['monthly_summary'],
            'filters' => [
                'year' => $year,
                'month' => $month,
            ],
            'share_token' => $token,
        ]);
    }
}
