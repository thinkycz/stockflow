<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected from settings to login', function (): void {
    $this->get('/settings')->assertRedirect('/login');
});

\test('admin can view the unified settings page', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne([
        'company_slack_channel' => '#company-operations',
    ]), User::class);

    $response = $this->be($user, 'users')->get('/settings', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'settings/Index');
    $response->assertJsonPath('props.auth.user.company_slack_channel', '#company-operations');
});

\test('admin can update the company Slack channel', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $response = $this->be($user, 'users')->post('/settings/slack', [
        'company_slack_channel' => '  #company-operations  ',
    ], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'settings/Index');
    \assertInertiaFlash($response, 'success', \__('Slack settings updated.'));
    $this->assertDatabaseHas('users', [
        'id' => $user->getKey(),
        'company_slack_channel' => '#company-operations',
    ]);
});

\test('blank company Slack channel is stored as null', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne([
        'company_slack_channel' => '#company-operations',
    ]), User::class);

    $this->be($user, 'users')->post('/settings/slack', [
        'company_slack_channel' => '   ',
    ], $this->inertiaHeaders())->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $user->getKey(),
        'company_slack_channel' => null,
    ]);
});

\test('company Slack channel is limited to one hundred characters', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $this->be($user, 'users')->post('/settings/slack', [
        'company_slack_channel' => \str_repeat('x', 101),
    ])->assertStatus(422);
});

\test('admin can update profile', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

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
    $userA = Typer::assertInstance(UserFactory::new()->admin()->createOne([
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

\test('admin can update password', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $response = $this->be($user, 'users')->post('/settings/password', [
        'password' => UserFactory::$password,
        'new_password' => 'new-password',
        'new_password_confirmation' => 'new-password',
    ], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'settings/Index');
    \assertInertiaFlash($response, 'success', \__('Password updated.'));

    $user->refresh();

    static::assertTrue(Resolver::resolveHasher()->check('new-password', $user->getAuthPassword()));
});

\test('password update revokes existing database tokens', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    Resolver::resolveDatabaseTokenGuard($user->getTable())->login($user);

    $this->assertDatabaseCount('database_tokens', 1);

    $this->be($user, 'users')->post('/settings/password', [
        'password' => UserFactory::$password,
        'new_password' => 'new-password',
        'new_password_confirmation' => 'new-password',
    ]);

    $this->assertDatabaseCount('database_tokens', 0);
});

\test('wrong current password is rejected', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $this->be($user, 'users')
        ->post('/settings/password', [
            'password' => 'wrong-password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ])
        ->assertStatus(422);
});

\test('validation failure on profile re-renders the unified settings page', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne([
        'email' => 'me@example.com',
    ]), User::class);
    UserFactory::new()->createOne(['email' => 'taken@example.com']);

    $this->be($user, 'users')
        ->from('/settings')
        ->post('/settings/profile', [
            'email' => 'taken@example.com',
            'locale' => 'en',
        ], $this->inertiaHeaders())
        ->assertRedirect('/settings')
        ->assertSessionHasErrors(['email']);

    $this->get('/settings', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'settings/Index');
});

\test('validation failure on password re-renders the unified settings page', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $this->be($user, 'users')
        ->from('/settings')
        ->post('/settings/password', [
            'password' => 'not-the-current-password',
            'new_password' => 'whatever',
            'new_password_confirmation' => 'whatever',
        ], $this->inertiaHeaders())
        ->assertRedirect('/settings')
        ->assertSessionHasErrors(['password']);

    $this->get('/settings', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'settings/Index');
});

\test('limited user cannot open settings', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $this->be($user, 'users')
        ->get('/settings', $this->inertiaHeaders())
        ->assertRedirect('/dashboard');
});

\test('limited user cannot change email or password through settings routes', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne([
        'email' => 'limited@example.com',
    ]), User::class);
    $originalPassword = $user->getAuthPassword();

    $this->be($user, 'users')
        ->post('/settings/profile', [
            'email' => 'changed@example.com',
            'locale' => 'cs',
        ], $this->inertiaHeaders())
        ->assertRedirect('/dashboard');

    $this->be($user, 'users')
        ->post('/settings/password', [
            'password' => UserFactory::$password,
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ], $this->inertiaHeaders())
        ->assertRedirect('/dashboard');

    $this->be($user, 'users')
        ->post('/settings/slack', [
            'company_slack_channel' => '#forbidden',
        ], $this->inertiaHeaders())
        ->assertRedirect('/dashboard');

    $user->refresh();

    static::assertSame('limited@example.com', $user->getEmail());
    static::assertSame($originalPassword, $user->getAuthPassword());
    static::assertNull($user->getCompanySlackChannel());
});
