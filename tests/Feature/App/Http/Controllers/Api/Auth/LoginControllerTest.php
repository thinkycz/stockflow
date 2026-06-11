<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\LoginController;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;

\test('user can login with valid credentials', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'login@example.com',
    ]);
    \expect($user)->toBeInstanceOf(User::class);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(LoginController::class), [
        'email' => 'login@example.com',
        'password' => 'password',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(200);
    $response->assertJsonPath('data.attributes.email', 'login@example.com');
    $response->assertCookie(Resolver::resolveDatabaseTokenGuard($user->getTable())->cookieName());
});

\test('login fails with wrong password', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'login@example.com',
    ]);
    \expect($user)->toBeInstanceOf(User::class);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(LoginController::class), [
        'email' => 'login@example.com',
        'password' => 'wrong-password',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(422);
});

\test('login fails with unknown email', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(LoginController::class), [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(422);
});

\test('login creates database token for user', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'login@example.com',
    ]);
    \expect($user)->toBeInstanceOf(User::class);

    $this->assertDatabaseCount('database_tokens', 0);

    $this->postJson(Resolver::resolveUrlGenerator()->action(LoginController::class), [
        'email' => 'login@example.com',
        'password' => 'password',
    ], ['Accept' => 'application/vnd.api+json'])->assertStatus(200);

    $this->assertDatabaseCount('database_tokens', 1);
});
