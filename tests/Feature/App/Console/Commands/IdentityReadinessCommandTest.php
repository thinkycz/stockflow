<?php

declare(strict_types=1);

use App\Enums\StoreStatusEnum;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('identity readiness fails without a main administrator', function (): void {
    $this->artisan('stockflow:identity:diagnose')
        ->expectsOutputToContain('[FAIL] ' . Typer::assertString(\__('Exactly one main administrator')))
        ->assertFailed();
});

\test('identity readiness rejects the known demo credential', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->password('password')->createOne([
        'email' => 'test@test.com',
    ]), User::class);
    $admin->provisionWarehouse();

    $this->artisan('stockflow:identity:diagnose')
        ->expectsOutputToContain('[FAIL] ' . Typer::assertString(\__('Demo credential removed')))
        ->assertFailed();
});

\test('identity readiness accepts one securely provisioned administrator', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->password('StrongPassword123!')->createOne([
        'email' => 'owner@example.com',
    ]), User::class);
    $admin->provisionWarehouse();

    $this->artisan('stockflow:identity:diagnose')
        ->expectsOutputToContain('[OK] ' . Typer::assertString(\__('Exactly one main administrator')))
        ->expectsOutputToContain('[OK] ' . Typer::assertString(\__('Demo credential removed')))
        ->expectsOutputToContain('[OK] ' . Typer::assertString(\__('Exactly one active main warehouse')))
        ->assertSuccessful();
});

\test('identity readiness rejects an inactive warehouse', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->password('StrongPassword123!')->createOne([
        'email' => 'owner@example.com',
    ]), User::class);
    $warehouse = $admin->provisionWarehouse();
    $warehouse->update(['status' => StoreStatusEnum::INACTIVE->value]);

    $this->artisan('stockflow:identity:diagnose')
        ->expectsOutputToContain('[FAIL] ' . Typer::assertString(\__('Exactly one active main warehouse')))
        ->assertFailed();
});
