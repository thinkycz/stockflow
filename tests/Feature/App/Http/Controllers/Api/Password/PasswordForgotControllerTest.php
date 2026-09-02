<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Password\PasswordForgotController;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Notifications\PasswordResetNotification;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('known email sends reset link without changing password or sessions', function (): void {
    Notification::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne([
        'email' => 'forgot@example.com',
    ]), User::class);
    $originalHash = $user->getAuthPassword();
    DatabaseToken::inject()->login($user->getTable(), $user);

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => $user->getEmail(),
    ]);

    $response->assertNoContent();
    $user->refresh();

    \expect($user->getAuthPassword())->toBe($originalHash);
    $this->assertDatabaseCount('database_tokens', 1);
    $this->assertDatabaseHas('user_password_resets', ['email' => $user->getEmail()]);
    Notification::assertSentTo($user, PasswordResetNotification::class);
});

\test('unknown email has the same public response as a known email', function (): void {
    Notification::fake();

    $known = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $knownResponse = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => $known->getEmail(),
    ]);
    $unknownResponse = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => 'nobody@example.com',
    ]);

    $knownResponse->assertNoContent();
    $unknownResponse->assertNoContent();
});

\test('notification dispatch failure removes the reset token without exposing the account', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    Notification::shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('Notification transport unavailable.'));

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class), [
        'email' => $user->getEmail(),
    ]);

    $response->assertNoContent();
    $this->assertDatabaseMissing('user_password_resets', ['email' => $user->getEmail()]);
});

\test('password reset link request requires email', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(PasswordForgotController::class));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('email');
});
