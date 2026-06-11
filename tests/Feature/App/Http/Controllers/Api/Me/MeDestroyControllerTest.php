<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Me\MeDestroyController;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;

\test('authenticated user can delete their account', function (): void {
    $me = UserFactory::new()->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $response = $this->be($me)->postJson(Resolver::resolveUrlGenerator()->action(MeDestroyController::class));

    $response->assertNoContent();

    $this->assertDatabaseMissing('users', ['id' => $me->getKey()]);
});

\test('unauthenticated user cannot delete account', function (): void {
    $response = $this->postJson(Resolver::resolveUrlGenerator()->action(MeDestroyController::class));

    $response->assertUnauthorized();
});
