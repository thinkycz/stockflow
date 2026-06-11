<?php

declare(strict_types=1);

use App\Http\Controllers\Api\EmailVerification\EmailVerificationResendController;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Thinkycz\LaravelCore\Notifications\EmailVerificationNotification;
use Thinkycz\LaravelCore\Support\Resolver;

\beforeEach(function (): void {
    Notification::fake();
});

\test('email verification notification can be resent', function (): void {
    $user = UserFactory::new()->unverified()->createOne();
    \expect($user)->toBeInstanceOf(User::class);

    $data = [
        'email' => $user->getEmail(),
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationResendController::class), $data);

    $response->assertNoContent();

    Notification::assertSentTo($user, EmailVerificationNotification::class);
});

\test('email verification notification not resent if already verified', function (): void {
    $user = UserFactory::new()->createOne();
    \expect($user)->toBeInstanceOf(User::class);

    $data = [
        'email' => $user->getEmail(),
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationResendController::class), $data);

    $response->assertNoContent();

    Notification::assertNothingSent();
});

\test('email verification requires valid email', function (): void {
    $data = [
        'email' => 'invalid-email',
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationResendController::class), $data);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors(['email']);
});

\test('email verification requires existing email', function (): void {
    $data = [
        'email' => 'nonexistent@example.com',
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationResendController::class), $data);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors(['email']);
});
