<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Me\MeDestroyController;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('csrf cookie endpoint issues a readable token', function (): void {
    $response = $this->getJson('/api/v1/csrf-cookie');

    $response->assertNoContent();
    $response->assertCookie('XSRF-TOKEN');

    $cookie = $response->getCookie('XSRF-TOKEN', false);
    \expect($cookie)->not->toBeNull()
        ->and($cookie?->isHttpOnly())->toBeFalse()
        ->and($cookie?->getValue())->toMatch('/^[A-Za-z0-9]{64}$/');
});

\test('cookie authenticated mutation without csrf token is rejected before mutation', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $token = DatabaseToken::inject()->login($user->getTable(), $user);
    $bearer = Typer::assertNotNull($token->bearer);
    $cookieName = Resolver::resolveDatabaseTokenGuard($user->getTable())->cookieName();

    $response = $this
        ->withCredentials()
        ->withHeader('Origin', 'https://attacker.example')
        ->withUnencryptedCookie($cookieName, $bearer)
        ->postJson(Resolver::resolveUrlGenerator()->action(MeDestroyController::class));

    $response->assertStatus(419);
    $response->assertJson(['code' => 0]);
    $this->assertDatabaseHas('users', ['id' => $user->getKey()]);
});

\test('cookie authenticated mutation accepts matching csrf token', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $token = DatabaseToken::inject()->login($user->getTable(), $user);
    $bearer = Typer::assertNotNull($token->bearer);
    $cookieName = Resolver::resolveDatabaseTokenGuard($user->getTable())->cookieName();
    $csrfToken = \str_repeat('a', 64);

    $response = $this
        ->withCredentials()
        ->withUnencryptedCookie($cookieName, $bearer)
        ->withUnencryptedCookie('XSRF-TOKEN', $csrfToken)
        ->postJson(
            Resolver::resolveUrlGenerator()->action(MeDestroyController::class),
            [],
            ['X-XSRF-TOKEN' => $csrfToken],
        );

    $response->assertNoContent();
    $this->assertDatabaseMissing('users', ['id' => $user->getKey()]);
});

\test('bearer authenticated mutation does not require csrf token', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $token = DatabaseToken::inject()->login($user->getTable(), $user);

    $response = $this
        ->withToken(Typer::assertNotNull($token->bearer))
        ->postJson(Resolver::resolveUrlGenerator()->action(MeDestroyController::class));

    $response->assertNoContent();
    $this->assertDatabaseMissing('users', ['id' => $user->getKey()]);
});

\test('a stale authentication cookie does not csrf-block an unauthenticated login', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne([
        'email' => 'stale-cookie-login@example.com',
    ]), User::class);
    $cookieName = Resolver::resolveDatabaseTokenGuard($user->getTable())->cookieName();

    $this->withUnencryptedCookie($cookieName, '999999|revoked-token')
        ->postJson(Resolver::resolveUrlGenerator()->action(LoginController::class), [
            'email' => $user->getEmail(),
            'password' => 'password',
        ], ['Accept' => 'application/vnd.api+json'])
        ->assertOk()
        ->assertJsonPath('data.attributes.email', $user->getEmail());
});
