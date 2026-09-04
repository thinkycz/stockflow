<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Domain\Workforce\WorkforceManagementService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftValidity;
use App\Models\Shift;
use App\Models\Store;
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
            'allow_overlap' => $validity->allowOverlap()->nullable()->toArray(),
        ]);

        $workerId = $validated->parseInt('worker_id');
        $worker = Typer::assertInstance(Worker::query()->find($workerId), Worker::class);
        $store = Typer::assertInstance(Store::query()->find($shift->getStoreId()), Store::class);
        $date = $validated->assertString('date');
        $startTime = $validated->assertString('start_time');
        $endTime = $validated->assertString('end_time');
        (new WorkforceManagementService())->updateShift(
            $admin,
            $store,
            $shift,
            $worker,
            $date,
            $startTime,
            $endTime,
            $validated->parseBool('allow_overlap'),
        );

        Inertia::flash('success', \__('Shift updated.'));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'month' => $request->query('month'),
            'year' => $request->query('year'),
        ]);
    }
}
