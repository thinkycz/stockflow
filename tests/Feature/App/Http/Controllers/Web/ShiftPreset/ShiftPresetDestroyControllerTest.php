<?php

declare(strict_types=1);

use App\Models\ShiftPreset;
use App\Models\Store;

\test('admin can delete a preset without changing shifts', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $preset = ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
    ]);

    $this->be($admin, 'users')
        ->delete("/shift-presets/{$preset->getKey()}", [], $this->inertiaHeaders())
        ->assertRedirect();

    \expect(ShiftPreset::query()->whereKey($preset->getKey())->exists())->toBeFalse();
});
