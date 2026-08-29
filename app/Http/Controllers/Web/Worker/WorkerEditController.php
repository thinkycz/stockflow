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

class WorkerEditController
{
    use ValidatesWebRequests;

    /**
     * Show the edit worker form.
     */
    public function edit(Worker $worker): Response
    {
        return Inertia::render('workers/Edit', [
            'worker' => [
                'id' => $worker->getKey(),
                'first_name' => $worker->getFirstName(),
                'last_name' => $worker->getLastName(),
                'hourly_rate' => $worker->getHourlyRate(),
                'calendar_color' => $worker->getStoredCalendarColor(),
                'effective_calendar_color' => $worker->getCalendarColor(),
                'automatic_calendar_color' => $worker->getAutomaticCalendarColor(),
                'attendance_rating_enabled' => $worker->isAttendanceRatingEnabled(),
            ],
            'calendar_colors' => Worker::calendarColors(),
        ]);
    }

    /**
     * Persist worker updates.
     */
    public function update(Request $request, Worker $worker): RedirectResponse
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

        (new AdministrationManagementService())->updateWorker(
            $admin,
            $worker,
            $validated->assertString('first_name'),
            $validated->assertString('last_name'),
            $validated->parseFloat('hourly_rate'),
            $validated->assertNullableString('calendar_color'),
            $validated->has('attendance_rating_enabled')
                ? $validated->parseBool('attendance_rating_enabled')
                : $worker->isAttendanceRatingEnabled(),
        );

        Inertia::flash('success', \__('Worker updated.'));

        return Resolver::resolveRedirector()->route('workers.index');
    }
}
