<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftValidity;
use App\Models\Shift;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftUpdateController
{
    use ValidatesWebRequests;

    /**
     * Persist shift updates.
     */
    public function __invoke(Request $request, Shift $shift): RedirectResponse
    {
        $admin = User::mustAuth();
        $validity = ShiftValidity::inject($admin->getKey());

        $validated = $this->validateRequest($request, [
            'worker_id' => $validity->workerId()->required()->toArray(),
            'date' => $validity->date()->required()->toArray(),
            'start_time' => $validity->startTime()->required()->toArray(),
            'end_time' => $validity->endTime()->required()->toArray(),
        ]);

        $workerId = $validated->parseInt('worker_id');
        $attributes = [
            'worker_id' => $workerId,
            'date' => $validated->assertString('date'),
            'start_time' => $validated->assertString('start_time'),
            'end_time' => $validated->assertString('end_time'),
        ];

        if ($workerId !== $shift->getWorkerId()) {
            $worker = Typer::assertInstance(Worker::query()->find($workerId), Worker::class);
            $attributes['hourly_rate'] = $worker->getHourlyRate();
        }

        $shift->update($attributes);

        Inertia::flash('success', \__('Shift updated.'));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'month' => $request->query('month'),
            'year' => $request->query('year'),
        ]);
    }
}
