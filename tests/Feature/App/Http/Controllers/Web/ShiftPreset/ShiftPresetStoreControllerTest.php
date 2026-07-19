<?php

declare(strict_types=1);

use App\Models\ShiftPreset;
use App\Models\Store;
use Database\Factories\UserFactory;

\test('admin can create a preset for the active store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);

    $this->be($admin, 'users')->post('/shift-presets', [
        'name' => '  Morning  ',
        'start_time' => '10:00',
        'end_time' => '15:00',
    ], $this->inertiaHeaders())->assertRedirect();

    $preset = ShiftPreset::query()->first();
    \expect($preset)->not->toBeNull()
        ->and($preset->getStoreId())->toBe($store->getKey())
        ->and($preset->getName())->toBe('Morning');
});

\test('preset times use quarter-hour increments and end after start', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);

    $this->be($admin, 'users')->post('/shift-presets', [
        'name' => 'Invalid step',
        'start_time' => '10:07',
        'end_time' => '15:00',
    ], $this->inertiaHeaders())->assertStatus(422);

    $this->be($admin, 'users')->post('/shift-presets', [
        'name' => 'Overnight',
        'start_time' => '21:00',
        'end_time' => '05:00',
    ], $this->inertiaHeaders())->assertStatus(422);

    \expect(ShiftPreset::query()->count())->toBe(0);
});

\test('preset name must be unique within the active store', function (): void {
    [$admin] = \createIsolatedUserWithWarehouse();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    ShiftPreset::factory()->create([
        'user_id' => $admin->getKey(),
        'store_id' => $store->getKey(),
        'name' => 'Morning',
    ]);

    $this->be($admin, 'users')->post('/shift-presets', [
        'name' => 'Morning',
        'start_time' => '12:00',
        'end_time' => '18:00',
    ], $this->inertiaHeaders())->assertStatus(422);

    \expect(ShiftPreset::query()->count())->toBe(1);
});

\test('limited user cannot create a shift preset', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey(), 'is_warehouse' => false]);
    $limited = UserFactory::new()->limited($store)->createOne();

    $this->be($limited, 'users')->post('/shift-presets', [
        'name' => 'Morning',
        'start_time' => '10:00',
        'end_time' => '15:00',
    ], $this->inertiaHeaders())->assertRedirect('/dashboard');

    \expect(ShiftPreset::query()->count())->toBe(0);
});
