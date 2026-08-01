<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\ChecklistDay;
use App\Models\Store;

\test('daily checklist command is idempotent and skips warehouse and inactive stores', function (): void {
    [$user, $warehouse] = \createIsolatedUserWithWarehouse();
    $retail = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);
    $inactive = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false, 'status' => StoreStatusEnum::INACTIVE->value]);

    $this->artisan('stockflow:generate-daily-checklists', ['--date' => '2026-08-01'])->assertSuccessful();
    $this->artisan('stockflow:generate-daily-checklists', ['--date' => '2026-08-01'])->assertSuccessful();

    \expect(ChecklistDay::query()->where('store_id', $retail->getKey())->whereDate('date', '2026-08-01')->count())->toBe(1)
        ->and(ChecklistDay::query()->where('store_id', $warehouse->getKey())->count())->toBe(0)
        ->and(ChecklistDay::query()->where('store_id', $inactive->getKey())->count())->toBe(0);
});
