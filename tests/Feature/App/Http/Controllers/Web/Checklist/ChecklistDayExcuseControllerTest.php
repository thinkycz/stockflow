<?php

declare(strict_types=1);

use App\Models\ChecklistEvent;
use App\Models\Store;
use App\Services\ChecklistService;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;

\test('admin excuses and restores a checklist day with audit events', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $this->withSession(\activeStoreSession($store));
    $day = (new ChecklistService())->ensureDay($store, CarbonImmutable::now('Europe/Prague'));

    $this->be($admin, 'users')->put(\route('checklist-days.excuse', $day->getKey()), ['reason' => 'Státní svátek'])
        ->assertRedirect();
    \expect($day->fresh()?->isExcused())->toBeTrue();

    $this->be($admin, 'users')->delete(\route('checklist-days.excuse.destroy', $day->getKey()), ['reason' => 'Oprava'])
        ->assertRedirect();
    \expect($day->fresh()?->isExcused())->toBeFalse()
        ->and(ChecklistEvent::query()->count())->toBe(2);
});
