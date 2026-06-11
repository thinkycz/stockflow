<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Me\MeShowController;
use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;

\test('authenticated user can view their profile', function (): void {
    $me = UserFactory::new()->createOne();
    \expect($me)->toBeInstanceOf(User::class);

    $response = $this->be($me)->getJson(Resolver::resolveUrlGenerator()->action(MeShowController::class));

    $response->assertOk();
});

\test('unauthenticated user cannot view profile', function (): void {
    $response = $this->getJson(Resolver::resolveUrlGenerator()->action(MeShowController::class));

    $response->assertUnauthorized();
});
