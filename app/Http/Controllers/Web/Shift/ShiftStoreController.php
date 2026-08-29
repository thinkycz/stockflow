<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Shift;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ShiftValidity;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\WorkforceManagementService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ShiftStoreController
{
    use ValidatesWebRequests;

    /**
     * Persist a new shift.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $validity = ShiftValidity::inject($admin->getKey());
        $store = ActiveStoreResolver::resolve($request, $admin);

        if (!$store instanceof Store) {
            \abort(404);
        }

        $validated = $this->validateRequest($request, [
            'worker_id' => $validity->workerId()->required()->toArray(),
            'date' => $validity->date()->required()->toArray(),
            'start_time' => $validity->startTime()->required()->toArray(),
            'end_time' => $validity->endTime()->required()->toArray(),
            'allow_overlap' => $validity->allowOverlap()->nullable()->toArray(),
        ]);

        $workerId = $validated->parseInt('worker_id');
        $worker = Typer::assertInstance(Worker::query()->find($workerId), Worker::class);
        $date = $validated->assertString('date');
        $startTime = $validated->assertString('start_time');
        $endTime = $validated->assertString('end_time');
        (new WorkforceManagementService())->createShift(
            $admin,
            $store,
            $worker,
            $date,
            $startTime,
            $endTime,
            $validated->parseBool('allow_overlap'),
        );

        Inertia::flash('success', \__('Shift created.'));

        return Resolver::resolveRedirector()->route('shifts.index', [
            'month' => $request->query('month'),
            'year' => $request->query('year'),
        ]);
    }
}
