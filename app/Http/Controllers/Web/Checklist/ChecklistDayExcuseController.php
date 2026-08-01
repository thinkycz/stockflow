<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Checklist;

use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ChecklistValidity;
use App\Models\ChecklistDay;
use App\Models\Store;
use App\Models\User;
use App\Services\ChecklistService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;

class ChecklistDayExcuseController
{
    use ValidatesWebRequests;

    /**
     * Mark a checklist day as excused.
     */
    public function update(Request $request, ChecklistDay $checklistDay): RedirectResponse
    {
        return $this->change($request, $checklistDay, true);
    }

    /**
     * Revoke an existing checklist-day excuse.
     */
    public function destroy(Request $request, ChecklistDay $checklistDay): RedirectResponse
    {
        return $this->change($request, $checklistDay, false);
    }

    /**
     * Persist the audited excuse state change.
     */
    private function change(Request $request, ChecklistDay $day, bool $excused): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);
        if (!$store instanceof Store || $store->getKey() !== $day->getStoreId()) { \abort(404); }
        $validity = ChecklistValidity::inject($admin->getKey());
        $validated = $this->validateRequest($request, ['reason' => $validity->reason()->required()->toArray()]);
        (new ChecklistService())->excuseDay($day, $admin, $validated->assertString('reason'), $excused);
        Inertia::flash('success', $excused ? \__('Checklist day excused.') : \__('Checklist day restored.'));

        return Resolver::resolveRedirector()->route('checklists.index', ['tab' => 'history', 'day_id' => $day->getKey()]);
    }
}
