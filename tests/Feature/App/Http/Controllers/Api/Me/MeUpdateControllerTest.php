<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Me\MeUpdateController;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;

\test('authenticated user can update their profile', function (): void {
    $me = UserFactory::new()->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $response = $this->be($me)->post(Resolver::resolveUrlGenerator()->action(MeUpdateController::class), [
        'email' => $me->getEmail(),
        'locale' => 'en',
    ]);

    $response->assertOk();
});

\test('unauthenticated user cannot update profile', function (): void {
    $response = $this->post(Resolver::resolveUrlGenerator()->action(MeUpdateController::class), [
        'email' => 'updated@example.com',
        'locale' => 'en',
    ]);

    $response->assertUnauthorized();
});

\test('validation fails with invalid data', function (): void {
    $me = UserFactory::new()->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $response = $this->be($me)->postJson(Resolver::resolveUrlGenerator()->action(MeUpdateController::class), [
        'email' => 'invalid-email',
        'locale' => 'invalid-locale',
    ]);

    $response->assertUnprocessable();

    $response->assertJsonValidationErrors([
        'email',
        'locale',
    ]);
});

\test('email must be unique', function (): void {
    $me = UserFactory::new()->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $existingUser = UserFactory::new()->createOne();
    \expect($existingUser)->toBeInstanceOf(User::class);

    $response = $this->be($me)->postJson(Resolver::resolveUrlGenerator()->action(MeUpdateController::class), [
        'email' => $existingUser->getEmail(),
    ]);

    $response->assertUnprocessable();
});

\test('update is rate limited after max attempts', function (): void {
    $me = UserFactory::new()->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $url = Resolver::resolveUrlGenerator()->action(MeUpdateController::class);

    for ($i = 0; $i < 5; ++$i) {
        $response = $this->be($me)->postJson($url, [
            'email' => "test{$i}@example.com",
            'locale' => 'en',
        ]);
        $response->assertOk();
    }

    $response = $this->be($me)->postJson($url, [
        'email' => 'throttled@example.com',
        'locale' => 'en',
    ]);

    $response->assertTooManyRequests();
});
