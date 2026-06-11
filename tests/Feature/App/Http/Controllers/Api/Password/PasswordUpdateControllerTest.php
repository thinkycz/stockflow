<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Password\PasswordUpdateController;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use Thinkycz\LaravelCore\Support\Resolver;

\test('authenticated user can update password', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'update@example.com',
    ]);
    \expect($user)->toBeInstanceOf(User::class);

    $this->be($user, 'users');

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordUpdateController::class), [
        'password' => 'password',
        'new_password' => 'new-password',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(204);

    $user->refresh();
    \expect(Hash::check('new-password', (string) $user->getAuthPassword()))->toBeTrue();
});

\test('update fails with wrong current password', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'update@example.com',
    ]);
    \expect($user)->toBeInstanceOf(User::class);

    $this->be($user, 'users');

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordUpdateController::class), [
        'password' => 'wrong-password',
        'new_password' => 'new-password',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(422);
});

\test('update revokes existing database tokens', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'update@example.com',
    ]);
    \expect($user)->toBeInstanceOf(User::class);

    Resolver::resolveDatabaseTokenGuard($user->getTable())->login($user);
    $this->assertDatabaseCount('database_tokens', 1);

    $this->be($user, 'users');

    $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordUpdateController::class), [
        'password' => 'password',
        'new_password' => 'new-password',
    ], ['Accept' => 'application/vnd.api+json'])->assertStatus(204);

    $this->assertDatabaseCount('database_tokens', 0);
});

\test('unauthenticated user cannot update password', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordUpdateController::class), [
        'password' => 'password',
        'new_password' => 'new-password',
    ], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(401);
});
