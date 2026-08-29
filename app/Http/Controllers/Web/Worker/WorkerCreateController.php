<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Worker;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\WorkerValidity;
use App\Models\User;
use App\Models\Worker;
use App\Services\AdministrationManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Resolver;

class WorkerCreateController
{
    use ValidatesWebRequests;

    /**
     * Show the create worker form.
     */
    public function create(): Response
    {
        return Inertia::render('workers/Create', [
            'calendar_colors' => Worker::calendarColors(),
        ]);
    }

    /**
     * Persist a new worker.
     */
    public function store(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $validity = WorkerValidity::inject($admin->getKey());

        $validated = $this->validateRequest($request, [
            'first_name' => $validity->firstName()->required()->toArray(),
            'last_name' => $validity->lastName()->required()->toArray(),
            'hourly_rate' => $validity->hourlyRate()->required()->toArray(),
            'attendance_rating_enabled' => $validity->attendanceRatingEnabled()->nullable()->toArray(),
            'calendar_color' => $validity->calendarColor()->nullable()->toArray(),
        ]);

        (new AdministrationManagementService())->createWorker(
            $admin,
            $validated->assertString('first_name'),
            $validated->assertString('last_name'),
            $validated->parseFloat('hourly_rate'),
            $validated->assertNullableString('calendar_color'),
            $validated->has('attendance_rating_enabled')
                ? $validated->parseBool('attendance_rating_enabled')
                : true,
        );

        Inertia::flash('success', \__('Worker created.'));

        return Resolver::resolveRedirector()->route('workers.index');
    }
}
