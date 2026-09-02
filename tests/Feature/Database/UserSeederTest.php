<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ItemSeeder;
use Database\Seeders\RecipeCatalogSeeder;
use Database\Seeders\StoreSeeder;
use Database\Seeders\UserSeeder;

\test('user seeder provisions one admin and is idempotent', function (): void {
    \app(UserSeeder::class)->run();
    \app(UserSeeder::class)->run();

    $admin = User::query()->where('is_admin', true)->firstOrFail();
    \expect(User::query()->count())->toBe(1)
        ->and($admin->getEmail())->toBe('test@test.com')
        ->and($admin->getParentUserId())->toBeNull()
        ->and($admin->getAssignedStoreId())->toBeNull()
        ->and($admin->stores()->where('is_warehouse', true)->count())->toBe(1);
});

\test('user seeder refuses multiple main administrators', function (): void {
    UserFactory::new()->admin()->count(2)->create();

    \expect(fn() => \app(UserSeeder::class)->run())->toThrow(RuntimeException::class);
});

\test('demo seeders refuse to run outside local and testing', function (string $seeder): void {
    $this->app->detectEnvironment(static fn(): string => 'production');

    \expect(fn() => \app($seeder)->run())->toThrow(RuntimeException::class);
})->with([
    DatabaseSeeder::class,
    UserSeeder::class,
    StoreSeeder::class,
    ItemSeeder::class,
    RecipeCatalogSeeder::class,
]);
