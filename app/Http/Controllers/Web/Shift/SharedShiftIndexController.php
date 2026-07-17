<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Models\Shift;
use App\Models\Store;
use App\Models\Worker;
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
        $storeQuery = Store::query();
        Store::scopeForShiftShareToken($storeQuery, $token);
        $store = $storeQuery->first();

        if (!$store instanceof Store) {
            \abort(404);
        }

        $now = Carbon::now();
        $year = Typer::parseNullableInt($request->query('year')) ?? $now->year;
        $month = Typer::parseNullableInt($request->query('month')) ?? $now->month;

        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $store->getUserId());
        Worker::querySelect($workerQuery);

        $workers = [];

        foreach ($workerQuery->get() as $worker) {
            $workers[$worker->getKey()] = [
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

        $shifts = $shiftQuery->take(self::TAKE)->get()->map(
            static function (Shift $shift) use ($workers): array {
                $worker = $workers[$shift->getWorkerId()] ?? null;

                return [
                    'id' => $shift->getKey(),
                    'worker_name' => $worker['name'] ?? '',
                    'worker_color' => $worker['color'] ?? '#64748B',
                    'date' => $shift->getDate(),
                    'start_time' => $shift->getStartTimeShort(),
                    'end_time' => $shift->getEndTimeShort(),
                ];
            },
        )->all();

        return Inertia::render('public-shifts/Index', [
            'store' => [
                'name' => $store->getName(),
            ],
            'shifts' => $shifts,
            'filters' => [
                'year' => $year,
                'month' => $month,
            ],
            'share_token' => $token,
        ]);
    }
}
