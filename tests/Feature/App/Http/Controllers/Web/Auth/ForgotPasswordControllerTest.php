<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Notifications\PasswordNewPasswordSettedNotification;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest can view forgot password page', function (): void {
    $response = $this->get('/forgot-password', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/ForgotPassword');
});

\test('unknown email returns validation error', function (): void {
    $response = $this->post('/forgot-password', [
        'email' => 'nobody@example.com',
    ]);

    $response->assertStatus(422);
});

\test('known email updates password and sends notification', function (): void {
    Notification::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalHash = $user->getAuthPassword();

    $response = $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/ForgotPassword');
    \assertInertiaFlash($response, 'success', \__('A new password has been sent to your email address.'));

    $user->refresh();

    static::assertNotSame($originalHash, $user->getAuthPassword());

    Notification::assertSentTo($user, PasswordNewPasswordSettedNotification::class);
});

\test('known email revokes existing database tokens', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    DatabaseToken::inject()
        ->login($user->getTable(), $user);

    $this->assertDatabaseCount('database_tokens', 1);

    $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ]);

    $this->assertDatabaseCount('database_tokens', 0);
});
