<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Me\MeDestroyController;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('authenticated user can delete their account', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $me = UserFactory::new()->limited($store)->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $response = $this->be($me)->postJson(Resolver::resolveUrlGenerator()->action(MeDestroyController::class));

    $response->assertNoContent();

    $this->assertDatabaseMissing('users', ['id' => $me->getKey()]);
});

\test('unauthenticated user cannot delete account', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(MeDestroyController::class));

    $response->assertUnauthorized();
});

\test('main administrator cannot delete the company through the compatibility API', function (): void {
    $admin = UserFactory::new()->admin()->createOne();
    $store = Store::factory()->create(['user_id' => $admin->getKey()]);
    $this->be($admin, 'users')->postJson('/api/v1/me/destroy')->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $admin->getKey()]);
    $this->assertDatabaseHas('stores', ['id' => $store->getKey()]);
});

\test('administrator self-deletion preserves real authentication credentials', function (string $transport): void {
    [$admin, $warehouse] = \createIsolatedUserWithWarehouse();
    $token = DatabaseToken::inject()->login('users', $admin);
    $bearer = Typer::assertNotNull($token->bearer);
    $cookieName = Resolver::resolveDatabaseTokenGuard('users')->cookieName();
    $tokenAttributes = $token->fresh()?->getRawOriginal();
    if ($transport === 'cookie') {
        $this->withCredentials()->withUnencryptedCookie($cookieName, $bearer)
            ->withUnencryptedCookie('XSRF-TOKEN', 'admin-delete-csrf')
            ->withHeader('X-XSRF-TOKEN', 'admin-delete-csrf');
    } else {
        $this->withToken($bearer);
    }

    $response = $this->postJson('/api/v1/me/destroy');
    $response->assertForbidden()->assertCookieMissing($cookieName);
    $this->assertDatabaseHas('users', ['id' => $admin->getKey()]);
    $this->assertDatabaseHas('stores', ['id' => $warehouse->getKey()]);
    \expect($token->fresh()?->getRawOriginal())->toBe($tokenAttributes);

    Resolver::resolveAuthManager()->forgetGuards();
    $this->getJson('/api/v1/me/show')->assertOk()->assertJsonPath('data.attributes.email', $admin->getEmail());
})->with(['cookie', 'bearer']);
