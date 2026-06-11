<?php

declare(strict_types=1);

use App\Http\Resources\UserResource;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Typer;

\test('resource type is users', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $resource = new UserResource($user);
    $request = Request::create('/');

    static::assertSame('users', $resource->toType($request));
});

\test('resource attributes include email locale and verified at', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $resource = new UserResource($user);
    $request = Request::create('/');

    $attributes = $resource->toAttributes($request);

    static::assertSame($user->getEmail(), $attributes['email']);
    static::assertSame($user->getLocale(), $attributes['locale']);
    static::assertNotNull($attributes['email_verified_at']);
});

\test('resource id matches user key', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $resource = new UserResource($user);
    $request = Request::create('/');

    static::assertSame((string) $user->getKey(), $resource->toId($request));
});
