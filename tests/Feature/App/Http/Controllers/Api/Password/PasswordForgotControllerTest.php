<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Password\PasswordForgotController;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Notifications\PasswordNewPasswordSettedNotification;
use Thinkycz\LaravelCore\Notifications\PasswordResetNotification;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

\test('user can request password reset link', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'forgot@example.com',
    ]);
    \expect($user)->toBeInstanceOf(App\Models\User::class);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => $user->getEmail(),
    ]);

    $response->assertNoContent();
});

\test('user can request password reset link reset link flow', function (): void {
    Notification::fake();

    Config::inject()->assign('auth.defaults.passwords', 'users');
    Config::inject()->assign('auth.passwords.users.send_raw_password', false);

    $user = UserFactory::new()->createOne([
        'email' => 'forgot@example.com',
    ]);
    \expect($user)->toBeInstanceOf(App\Models\User::class);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => $user->getEmail(),
    ]);

    $response->assertNoContent();

    Notification::assertSentTo($user, PasswordResetNotification::class);
});

\test('user can request password reset link send raw password flow', function (): void {
    Notification::fake();

    Config::inject()->assign('auth.defaults.passwords', 'users');
    Config::inject()->assign('auth.passwords.users.send_raw_password', true);

    $user = UserFactory::new()->createOne([
        'email' => 'forgot@example.com',
    ]);
    \expect($user)->toBeInstanceOf(App\Models\User::class);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => $user->getEmail(),
    ]);

    $response->assertNoContent();

    Notification::assertSentTo($user, PasswordNewPasswordSettedNotification::class);
});

\test('password reset link request fails with invalid email', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('email');
});

\test('password reset link request requires email', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('email');
});
