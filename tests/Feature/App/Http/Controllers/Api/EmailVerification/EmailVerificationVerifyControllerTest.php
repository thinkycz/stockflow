<?php

declare(strict_types=1);

use App\Http\Controllers\Api\EmailVerification\EmailVerificationVerifyController;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Services\EmailBrokerService;
use Thinkycz\LaravelCore\Support\Resolver;

\test('email can be verified with valid token', function (): void {
    $user = UserFactory::new()->unverified()->createOne();
    \expect($user)->toBeInstanceOf(User::class);

    $token = EmailBrokerService::inject()->store($user->getTable(), $user->getEmailForVerification());

    $data = [
        'email' => $user->getEmail(),
        'token' => $token,
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationVerifyController::class), $data);

    $response->assertNoContent();

    $user->refresh();

    \expect($user->hasVerifiedEmail())->toBeTrue();
});

\test('already verified email returns no content', function (): void {
    $user = UserFactory::new()->createOne();
    \expect($user)->toBeInstanceOf(User::class);

    $token = EmailBrokerService::inject()->store($user->getTable(), $user->getEmailForVerification());

    $data = [
        'email' => $user->getEmail(),
        'token' => $token,
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationVerifyController::class), $data);

    $response->assertNoContent();

    $user->refresh();

    \expect($user->hasVerifiedEmail())->toBeTrue();
});

\test('verification fails with invalid token', function (): void {
    $user = UserFactory::new()->unverified()->createOne();
    \expect($user)->toBeInstanceOf(User::class);

    $data = [
        'email' => $user->getEmail(),
        'token' => 'invalid-token',
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationVerifyController::class), $data);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors(['token']);

    $user->refresh();

    \expect($user->hasVerifiedEmail())->toBeFalse();
});

\test('verification requires valid email', function (): void {
    $data = [
        'email' => 'invalid-email',
        'token' => 'any-token',
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationVerifyController::class), $data);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors(['email']);
});

\test('verification requires token', function (): void {
    $data = [
        'email' => 'test@example.com',
        'token' => '',
    ];

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(EmailVerificationVerifyController::class), $data);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors(['token']);
});
