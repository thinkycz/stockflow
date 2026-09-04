<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Checklist;

use App\Domain\Checklists\ChecklistService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ChecklistValidity;
use App\Models\ChecklistDay;
use App\Models\ChecklistItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Worker;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Thrower;

class ChecklistItemController
{
    use ValidatesWebRequests;

    /**
     * Complete or reopen a current-day checklist item.
     */
    public function __invoke(Request $request, ChecklistItem $checklistItem): RedirectResponse
    {
        $actor = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $actor);
        if (!$store instanceof Store || $store->isWarehouse()) { \abort(404); }
        if (!ChecklistDay::query()->whereKey($checklistItem->getChecklistDayId())->where('store_id', $store->getKey())->exists()) { \abort(404); }
        $validity = ChecklistValidity::inject($actor->resolveScopeUser()->getKey());
        $validated = $this->validateRequest($request, [
            'completed' => $validity->completed()->required()->toArray(),
            'worker_id' => $validity->workerId()->nullable()->toArray(),
            'lock_version' => $validity->lockVersion()->required()->toArray(),
        ]);
        $workerId = $validated->assertNullableInt('worker_id');
        $workerQuery = Worker::query()->where('user_id', $actor->resolveScopeUser()->getKey());
        Worker::scopeActive($workerQuery);
        $worker = $workerId === null ? null : $workerQuery->whereKey($workerId)->firstOrFail();
        try {
            (new ChecklistService())->updateItem($checklistItem, $store, $actor, $worker, $validated->parseBool('completed'), $validated->parseInt('lock_version'));
        } catch (RuntimeException) {
            Thrower::default()->message('lock_version', \__('The checklist was changed by another user. Refresh and try again.'))->throw();
        } catch (InvalidArgumentException $exception) {
            Thrower::default()->message('checklist', $exception->getMessage())->throw();
        }
        Inertia::flash('success', \__('Checklist updated.'));

        return Resolver::resolveRedirector()->route('dashboard');
    }
}
