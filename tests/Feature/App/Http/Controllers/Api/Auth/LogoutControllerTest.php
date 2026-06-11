<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\LogoutController;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;

\test('authenticated user can logout', function (): void {
    $user = UserFactory::new()->createOne([
        'email' => 'logout@example.com',
    ]);
    \expect($user)->toBeInstanceOf(User::class);

    $guard = Resolver::resolveDatabaseTokenGuard($user->getTable());
    $guard->login($user);
    $this->assertDatabaseCount('database_tokens', 1);

    $cookie = $guard->cookieName();
    $tokenModel = $user->databaseTokens()->getQuery()->first();

    $this->withCookie($cookie, (string) ($tokenModel?->getKey() ?? ''));

    $this->be($user, 'users');

    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(LogoutController::class), [], ['Accept' => 'application/vnd.api+json']);

    $response->assertStatus(204);
    $response->assertCookieExpired($cookie);
});

\test('logout fails for guest', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(LogoutController::class), [], ['Accept' => 'application/vnd.api+json']);

    $status = (int) $response->baseResponse->getStatusCode();
    \expect([403, 401, 427])->toContain($status);
});
