<?php

declare(strict_types=1);

use App\Enums\ChecklistShiftEnum;
use App\Enums\ChecklistTemplateScopeEnum;
use App\Models\ChecklistTemplateTask;
use App\Models\Store;
use Database\Factories\UserFactory;

\test('admin replaces one ordered template group transactionally', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $admin->setActiveStoreId($store->getKey());

    $this->be($admin, 'users')->put(\route('checklists.templates.update'), [
        'scope' => ChecklistTemplateScopeEnum::Weekly->value,
        'weekday' => 2,
        'shift' => ChecklistShiftEnum::Morning->value,
        'tasks' => [['text' => 'První'], ['text' => 'Druhý']],
    ])->assertRedirect(\route('checklists.index', ['scope' => 'weekly', 'weekday' => 2]));

    $texts = ChecklistTemplateTask::query()->where('store_id', $store->getKey())
        ->where('scope', 'weekly')->where('weekday', 2)->where('shift', 'morning')
        ->orderBy('position')->pluck('text')->all();
    \expect($texts)->toBe(['První', 'Druhý']);
});
