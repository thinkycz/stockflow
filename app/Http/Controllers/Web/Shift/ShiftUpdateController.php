<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftValidity;
use App\Models\Shift;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\ShiftAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;
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
            'allow_overlap' => $validity->allowOverlap()->nullable()->toArray(),
        ]);

        $workerId = $validated->parseInt('worker_id');
        $worker = Typer::assertInstance(Worker::query()->find($workerId), Worker::class);
        $store = Typer::assertInstance(Store::query()->find($shift->getStoreId()), Store::class);
        $date = $validated->assertString('date');
        $startTime = $validated->assertString('start_time');
        $endTime = $validated->assertString('end_time');
        $service = new ShiftAssignmentService();

        if (!$validated->parseBool('allow_overlap') && $service->findOverlaps(
            $admin,
            $store,
            $worker,
            $date,
            $startTime,
            $endTime,
            $shift->getKey(),
        )->isNotEmpty()) {
            Thrower::default()->message('overlap', \__('This shift overlaps an existing assignment.'))->throw();
        }

        $attributes = [
            'worker_id' => $workerId,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];

        if ($workerId !== $shift->getWorkerId()) {
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
