<?php

declare(strict_types=1);

use App\Enums\LimitedUserSectionEnum;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('incoming and consumption access are enforced independently', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Typer::assertInstance(Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]), Store::class);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne([
        'disabled_sections' => [LimitedUserSectionEnum::INCOMING->value],
    ]), User::class);

    $this->be($limited, 'users')
        ->get('/stock-movements/create?mode=incoming')
        ->assertRedirect('/dashboard');

    $this->be($limited, 'users')
        ->get('/stock-movements/create?mode=consumption')
        ->assertOk();
});

\test('disabled stock movement write is rejected before persistence', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Typer::assertInstance(Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]), Store::class);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne([
        'disabled_sections' => [LimitedUserSectionEnum::CONSUMPTION->value],
    ]), User::class);

    $this->be($limited, 'users')->post('/stock-movements', [
        'mode' => 'consumption',
        'store_id' => $store->getKey(),
        'items' => [],
    ])->assertRedirect('/dashboard');

    $this->assertDatabaseCount('stock_movements', 0);
});

\test('disabled stock movement item search returns forbidden JSON', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Typer::assertInstance(Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]), Store::class);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne([
        'disabled_sections' => [LimitedUserSectionEnum::INCOMING->value],
    ]), User::class);
    Item::factory()->create(['user_id' => $admin->getKey(), 'title' => 'Coffee']);

    $this->be($limited, 'users')
        ->getJson('/items/search?mode=incoming&q=coffee')
        ->assertForbidden();
});

\test('fixed section route rejects a limited user but not an admin', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);
    $store = Typer::assertInstance(Store::factory()->create([
        'user_id' => $admin->getKey(),
        'is_warehouse' => false,
    ]), Store::class);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne([
        'disabled_sections' => [LimitedUserSectionEnum::SHIFTS->value],
    ]), User::class);

    $this->be($limited, 'users')->get('/shifts')->assertRedirect('/dashboard');
    $this->be($admin, 'users')->get('/shifts')->assertOk();
});
