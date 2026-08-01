<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Checklist;

use App\Enums\ChecklistShiftEnum;
use App\Enums\ChecklistTemplateScopeEnum;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Http\Validation\ChecklistValidity;
use App\Models\Store;
use App\Models\User;
use App\Services\ChecklistService;
use App\Support\ActiveStoreResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class ChecklistTemplateController
{
    use ValidatesWebRequests;

    /**
     * Replace one ordered template group.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $admin = User::mustAuth();
        $store = ActiveStoreResolver::resolve($request, $admin);
        if (!$store instanceof Store || $store->isWarehouse()) { \abort(404); }
        $validity = ChecklistValidity::inject($admin->getKey());
        $validated = $this->validateRequest($request, [
            'scope' => $validity->scope()->required()->toArray(),
            'weekday' => $validity->weekday()->nullable()->toArray(),
            'shift' => $validity->shift()->required()->toArray(),
            'tasks' => $validity->tasks()->required()->toArray(),
            'tasks.*.text' => $validity->taskText()->required()->toArray(),
        ]);
        $scope = ChecklistTemplateScopeEnum::from($validated->assertString('scope'));
        $weekday = $validated->assertNullableInt('weekday');
        $texts = [];
        foreach ($validated->assertArray('tasks') as $row) {
            $texts[] = \mb_trim(Typer::assertString(Typer::assertStringKeyArray(Typer::assertArray($row))['text'] ?? null));
        }
        (new ChecklistService())->replaceTemplateGroup($store, $scope, $weekday, ChecklistShiftEnum::from($validated->assertString('shift')), $texts);
        Inertia::flash('success', \__('Checklist template saved.'));

        return Resolver::resolveRedirector()->route('checklists.index', ['scope' => $scope->value, 'weekday' => $weekday]);
    }
}
