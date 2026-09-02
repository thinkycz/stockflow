<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Attendance;

use App\Enums\AttendanceActionEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\AttendanceValidity;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Services\AttendanceService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class AttendanceActionController
{
    use ValidatesWebRequests;

    /**
     * Record the next attendance state transition.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = User::mustAuth();
        $owner = $user->resolveScopeUser();
        $store = ActiveStoreResolver::resolve($request, $user);
        if (!$store instanceof Store || $store->isWarehouse()) { \abort(404); }
        $validity = AttendanceValidity::inject($owner->getKey());
        $validated = $this->validateRequest($request, [
            'worker_id' => $validity->workerId()->required()->toArray(),
            'action' => $validity->action()->required()->toArray(),
            'confirm_without_shift' => $validity->confirmation()->nullable()->toArray(),
        ]);
        $workerQuery = Worker::query();
        Worker::scopeForUser($workerQuery, $owner);
        Worker::scopeActive($workerQuery);
        $worker = $workerQuery->whereKey($validated->parseInt('worker_id'))->firstOrFail();
        (new AttendanceService())->perform(
            $user,
            $store,
            $worker,
            AttendanceActionEnum::from($validated->assertString('action')),
            $validated->parseBool('confirm_without_shift'),
        );
        Inertia::flash('success', \__('Attendance recorded.'));

        return Resolver::resolveRedirector()->route('attendance.index');
    }
}
