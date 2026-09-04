<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Worker;

use App\Domain\Workforce\WorkerManagementService;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class WorkerRestoreController
{
    /**
     * Restore an archived worker to active selectors.
     */
    public function __invoke(Worker $worker): RedirectResponse
    {
        (new WorkerManagementService())->restoreWorker(User::mustAuth(), $worker);
        Inertia::flash('success', \__('Worker restored.'));

        return Resolver::resolveRedirector()->route('workers.index', ['status' => 'archived']);
    }
}
