<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Worker;

use App\Models\User;
use App\Models\Worker;
use App\Services\AdministrationManagementService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class WorkerDestroyController
{
    /**
     * Delete a worker.
     *
     * Blocks deletion when the worker has existing shifts so the
     * scheduling history is preserved (the shifts.worker_id FK uses
     * restrictOnDelete at the database level).
     */
    public function __invoke(Worker $worker): RedirectResponse
    {
        if (!(new AdministrationManagementService())->deleteWorker(User::mustAuth(), $worker)) {
            Inertia::flash('error', \__('Cannot delete a worker with existing shifts.'));

            return Resolver::resolveRedirector()->route('workers.index');
        }
        Inertia::flash('success', \__('Worker deleted.'));

        return Resolver::resolveRedirector()->route('workers.index');
    }
}
