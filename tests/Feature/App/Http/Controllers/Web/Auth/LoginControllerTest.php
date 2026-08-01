<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest can view login page', function (): void {
    $response = $this->get('/login', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/Login');
});

\test('user can login with database token cookie', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->post('/login', [
        'email' => $user->getEmail(),
        'password' => UserFactory::$password,
    ]);

    $response->assertRedirect('/dashboard');
    $response->assertCookie(Resolver::resolveDatabaseTokenGuard($user->getTable())->cookieName());
});

\test('failed login returns to the form with safe input and field errors', function (): void {
    $response = $this->from('/login')->post('/login', [
        'email' => 'missing@example.com',
        'password' => 'not-a-real-password',
    ], $this->inertiaHeaders());

    $response
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email')
        ->assertSessionHasInput('email', 'missing@example.com')
        ->assertSessionMissing('_old_input.password');

    $this->get('/login', $this->inertiaHeaders())
        ->assertOk()
        ->assertJsonPath('component', 'auth/Login')
        ->assertJsonPath('props.errors.email', \__('auth.failed'));
});
