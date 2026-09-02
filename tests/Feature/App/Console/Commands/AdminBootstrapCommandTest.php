<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('admin bootstrap creates the sole administrator and warehouse from hidden prompts', function (): void {
    $this->artisan('stockflow:admin:bootstrap', ['email' => 'owner@example.com'])
        ->expectsQuestion(Typer::assertString(\__('Administrator password')), 'StrongPassword123!')
        ->expectsQuestion(Typer::assertString(\__('Confirm administrator password')), 'StrongPassword123!')
        ->expectsOutputToContain(Typer::assertString(\__('Main administrator created.')))
        ->assertSuccessful();

    $admin = User::query()->firstOrFail();

    \expect(User::query()->count())->toBe(1)
        ->and($admin->getEmail())->toBe('owner@example.com')
        ->and($admin->isAdmin())->toBeTrue()
        ->and($admin->getParentUserId())->toBeNull()
        ->and($admin->getAssignedStoreId())->toBeNull()
        ->and(Resolver::resolveHasher()->check('StrongPassword123!', $admin->getAuthPassword()))->toBeTrue()
        ->and($admin->stores()->where('is_warehouse', true)->count())->toBe(1);
});

\test('admin bootstrap is a safe no-op for the matching administrator', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->password('OriginalPassword123!')->createOne([
        'email' => 'owner@example.com',
    ]), User::class);
    $admin->provisionWarehouse();
    $originalHash = $admin->getAuthPassword();

    $this->artisan('stockflow:admin:bootstrap', ['email' => 'owner@example.com'])
        ->expectsOutputToContain(Typer::assertString(\__('Main administrator already provisioned; no changes made.')))
        ->assertSuccessful();

    \expect($admin->refresh()->getAuthPassword())->toBe($originalHash)
        ->and(User::query()->count())->toBe(1)
        ->and($admin->stores()->where('is_warehouse', true)->count())->toBe(1);
});

\test('admin bootstrap rotates password explicitly and revokes tokens', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->password('OriginalPassword123!')->createOne([
        'email' => 'owner@example.com',
    ]), User::class);
    $admin->provisionWarehouse();
    DatabaseToken::inject()->login($admin->getTable(), $admin);

    $this->artisan('stockflow:admin:bootstrap', ['email' => 'owner@example.com', '--rotate' => true])
        ->expectsQuestion(Typer::assertString(\__('Administrator password')), 'ReplacementPassword123!')
        ->expectsQuestion(Typer::assertString(\__('Confirm administrator password')), 'ReplacementPassword123!')
        ->expectsOutputToContain(Typer::assertString(\__('Administrator password rotated.')))
        ->assertSuccessful();

    \expect(Resolver::resolveHasher()->check('ReplacementPassword123!', $admin->refresh()->getAuthPassword()))->toBeTrue();
    $this->assertDatabaseCount('database_tokens', 0);
});

\test('admin bootstrap rejects invalid existing identity states', function (string $state): void {
    if ($state === 'mismatched') {
        UserFactory::new()->admin()->createOne(['email' => 'different@example.com']);
    } elseif ($state === 'orphan') {
        UserFactory::new()->createOne(['email' => 'orphan@example.com']);
    } else {
        UserFactory::new()->admin()->count(2)->create();
    }

    $this->artisan('stockflow:admin:bootstrap', ['email' => 'owner@example.com'])
        ->assertFailed();
})->with(['mismatched', 'orphan', 'multiple-admins']);

\test('admin bootstrap requires a valid email and matching valid password', function (): void {
    $this->artisan('stockflow:admin:bootstrap', ['email' => 'not-an-email'])
        ->assertFailed();

    $this->artisan('stockflow:admin:bootstrap', ['email' => 'owner@example.com'])
        ->expectsQuestion(Typer::assertString(\__('Administrator password')), 'short')
        ->expectsQuestion(Typer::assertString(\__('Confirm administrator password')), 'different')
        ->assertFailed();
});
