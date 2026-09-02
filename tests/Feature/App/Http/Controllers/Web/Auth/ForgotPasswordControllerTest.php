<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Notifications\PasswordResetNotification;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest can view forgot password page', function (): void {
    $response = $this->get('/forgot-password', $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/ForgotPassword');
});

\test('known email sends reset link without changing password or sessions', function (): void {
    Notification::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $originalHash = $user->getAuthPassword();
    DatabaseToken::inject()->login($user->getTable(), $user);

    $response = $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ], $this->inertiaHeaders());

    $response->assertOk();
    $response->assertJsonPath('component', 'auth/ForgotPassword');
    \assertInertiaFlash($response, 'success', \__(PasswordBroker::RESET_LINK_SENT));

    $user->refresh();

    \expect($user->getAuthPassword())->toBe($originalHash);
    $this->assertDatabaseCount('database_tokens', 1);
    $this->assertDatabaseHas('user_password_resets', ['email' => $user->getEmail()]);
    Notification::assertSentTo($user, PasswordResetNotification::class);
});

\test('unknown email has the same public response as a known email', function (): void {
    Notification::fake();

    $known = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $knownResponse = $this->post('/forgot-password', [
        'email' => $known->getEmail(),
    ], $this->inertiaHeaders());

    $unknownResponse = $this->post('/forgot-password', [
        'email' => 'nobody@example.com',
    ], $this->inertiaHeaders());

    $knownResponse->assertOk();
    $unknownResponse->assertOk();
    $knownResponse->assertJsonPath('component', 'auth/ForgotPassword');
    $unknownResponse->assertJsonPath('component', 'auth/ForgotPassword');
    \assertInertiaFlash($knownResponse, 'success', \__(PasswordBroker::RESET_LINK_SENT));
    \assertInertiaFlash($unknownResponse, 'success', \__(PasswordBroker::RESET_LINK_SENT));
});

\test('notification dispatch failure removes the reset token without exposing the account', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    Notification::shouldReceive('send')
        ->once()
        ->andThrow(new RuntimeException('Notification transport unavailable.'));

    $response = $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ], $this->inertiaHeaders());

    $response->assertOk();
    \assertInertiaFlash($response, 'success', \__(PasswordBroker::RESET_LINK_SENT));
    $this->assertDatabaseMissing('user_password_resets', ['email' => $user->getEmail()]);
});

\test('permanent queued delivery failure removes the reset token', function (): void {
    Notification::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $this->post('/forgot-password', [
        'email' => $user->getEmail(),
    ]);

    Notification::assertSentTo(
        $user,
        PasswordResetNotification::class,
        static function (PasswordResetNotification $notification): bool {
            $notification->failed(new RuntimeException('Permanent mail delivery failure.'));

            return true;
        },
    );
    $this->assertDatabaseMissing('user_password_resets', ['email' => $user->getEmail()]);
});

\test('stale queued delivery failure preserves a newer reset token', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $broker = Resolver::resolvePasswordBroker('users');
    $staleToken = $broker->createToken($user);
    $notification = PasswordResetNotification::inject('users', $staleToken, $user->getEmail());
    $freshToken = $broker->createToken($user);

    $notification->failed(new RuntimeException('Stale mail delivery failure.'));

    \expect($broker->tokenExists($user, $freshToken))->toBeTrue();
});

\test('reset notification renders an absolute localized action URL', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(['locale' => 'sk']), User::class);
    $notification = PasswordResetNotification::inject('users', 'reset-token', $user->getEmail())->locale('sk');
    $originalLocale = \app()->getLocale();

    try {
        \app()->setLocale('sk');
        $mail = $notification->toMail($user);
    } finally {
        \app()->setLocale($originalLocale);
    }

    $query = [];
    \parse_str((string) \parse_url((string) $mail->actionUrl, \PHP_URL_QUERY), $query);

    \expect($mail->subject)->toBe('Žiadosť o obnovenie hesla')
        ->and($mail->actionText)->toBe('Obnoviť heslo')
        ->and($mail->actionUrl)->toStartWith(Resolver::resolveUrlGenerator()->route('reset-password.show') . '?')
        ->and($query)->toBe([
            'guard' => 'users',
            'token' => 'reset-token',
            'email' => $user->getEmail(),
            'locale' => 'sk',
        ]);
});
