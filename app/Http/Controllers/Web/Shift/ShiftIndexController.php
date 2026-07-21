<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Models\Shift;
use App\Models\ShiftPreset;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
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
        $minutesByWorker = [];
        $salaryByWorker = [];

        if ($store instanceof Store) {
            $query = Shift::query();
            Shift::scopeForUser($query, $scopeUser);
            Shift::scopeForStore($query, $store->getKey());
            Shift::scopeForMonth($query, $year, $month);
            Shift::querySelect($query);
            $query->orderBy('date')->orderBy('start_time');

            foreach ($query->take(self::TAKE)->get() as $shift) {
                $workerId = $shift->getWorkerId();
                $durationMinutes = $shift->getDurationMinutes();
                $minutesByWorker[$workerId] = ($minutesByWorker[$workerId] ?? 0) + $durationMinutes;
                $salaryByWorker[$workerId] = ($salaryByWorker[$workerId] ?? 0)
                    + (($durationMinutes / 60) * $shift->getHourlyRate());
                $shifts[] = [
                    'id' => $shift->getKey(),
                    'worker_id' => $workerId,
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
        $workers = $workerModels->map(
            static function (Worker $worker): array {
                return [
                    'id' => $worker->getKey(),
                    'first_name' => $worker->getFirstName(),
                    'last_name' => $worker->getLastName(),
                    'color' => $worker->getCalendarColor(),
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
        ];

        if ($user->isAdmin()) {
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
            $props['worker_summary'] = $workerModels
                ->filter(static fn(Worker $worker): bool => isset($minutesByWorker[$worker->getKey()]))
                ->values()
                ->map(static function (Worker $worker) use ($minutesByWorker, $salaryByWorker): array {
                    $hours = ($minutesByWorker[$worker->getKey()] ?? 0) / 60;

                    return [
                        'worker_id' => $worker->getKey(),
                        'worker_name' => $worker->getFullName(),
                        'color' => $worker->getCalendarColor(),
                        'hours' => $hours,
                        'salary' => \round($salaryByWorker[$worker->getKey()] ?? 0, 2),
                    ];
                })
                ->all();
        }

        return Inertia::render('shifts/Index', $props);
    }
}
