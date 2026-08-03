<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Worker;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\WorkerValidity;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkerIndexController
{
    use ValidatesWebRequests;

    /**
     * Page size hint required by the web index controller architecture test.
     *
     * The workers list is bounded by the number of part-timers an admin
     * has provisioned; pagination is not exposed.
     */
    public const int TAKE = 1000;

    /**
     * Render the worker management page.
     */
    public function __invoke(Request $request): Response
    {
        $admin = User::mustAuth();
        $validity = WorkerValidity::inject($admin->getKey());

        $validated = $this->validateRequest($request, [
            'search' => $validity->search()->nullable()->toArray(),
        ]);

        $search = $validated->assertNullableString('search') ?? '';

        $baseQuery = Worker::query();
        Worker::scopeForUser($baseQuery, $admin);
        $query = Worker::querySelect($baseQuery)->orderBy('last_name')->orderBy('first_name');

        if ($search !== '') {
            Worker::scopeSearch($query, $search);
        }

        $rows = $query->take(self::TAKE)->get()->map(static function (Worker $worker): array {
            return [
                'id' => $worker->getKey(),
                'first_name' => $worker->getFirstName(),
                'last_name' => $worker->getLastName(),
                'color' => $worker->getCalendarColor(),
                'hourly_rate' => $worker->getHourlyRate(),
                'attendance_rating_enabled' => $worker->isAttendanceRatingEnabled(),
            ];
        })->all();

        return Inertia::render('workers/Index', [
            'workers' => $rows,
            'search' => $search,
        ]);
    }
}
