<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected from settings to login', function (): void {
    $this->get('/settings')->assertRedirect('/login');
});

\test('authenticated user can view the unified settings page', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->get('/settings', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'settings/Index');
});

\test('authenticated user can update profile', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->post('/settings/profile', [
        'email' => 'updated@example.com',
        'locale' => 'cs',
    ], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'settings/Index');
    \assertInertiaFlash($response, 'success', \__('Profile updated.'));
    $this->assertDatabaseHas('users', [
        'id' => $user->getKey(),
        'email' => 'updated@example.com',
        'locale' => 'cs',
    ]);
});

\test('profile email must be unique', function (): void {
    $userA = Typer::assertInstance(UserFactory::new()->createOne([
        'email' => 'a@example.com',
    ]), User::class);
    $userB = Typer::assertInstance(UserFactory::new()->createOne([
        'email' => 'b@example.com',
    ]), User::class);

    $this->be($userA, 'users')
        ->post('/settings/profile', [
            'email' => 'b@example.com',
            'locale' => 'en',
        ])
        ->assertStatus(422);
});

\test('authenticated user can update password', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->post('/settings/password', [
        'password' => UserFactory::$password,
        'new_password' => 'new-password',
    ], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'settings/Index');
    \assertInertiaFlash($response, 'success', \__('Password updated.'));

    $user->refresh();

    static::assertTrue(Resolver::resolveHasher()->check('new-password', $user->getAuthPassword()));
});

\test('password update revokes existing database tokens', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    Resolver::resolveDatabaseTokenGuard($user->getTable())->login($user);

    $this->assertDatabaseCount('database_tokens', 1);

    $this->be($user, 'users')->post('/settings/password', [
        'password' => UserFactory::$password,
        'new_password' => 'new-password',
    ]);

    $this->assertDatabaseCount('database_tokens', 0);
});

\test('wrong current password is rejected', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $this->be($user, 'users')
        ->post('/settings/password', [
            'password' => 'wrong-password',
            'new_password' => 'new-password',
        ])
        ->assertStatus(422);
});

\test('validation failure on profile re-renders the unified settings page', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne([
        'email' => 'me@example.com',
    ]), User::class);

    // A duplicate email must trigger a 422 with the Inertia component
    // pointing back at the actual settings page (settings/Index), not
    // the legacy settings/Profile component the bootstrap match
    // expression used to map to.
    $response = $this->be($user, 'users')
        ->post('/settings/profile', [
            'email' => 'taken@example.com',
        ], $this->inertiaHeaders())
        ->assertStatus(422);

    $response->assertJsonPath('component', 'settings/Index');
});

\test('validation failure on password re-renders the unified settings page', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')
        ->post('/settings/password', [
            'password' => 'not-the-current-password',
            'new_password' => 'whatever',
        ], $this->inertiaHeaders())
        ->assertStatus(422);

    $response->assertJsonPath('component', 'settings/Index');
});
