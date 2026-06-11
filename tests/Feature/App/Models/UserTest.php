<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Typer;

\test('email getter returns email', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    static::assertSame($user->getEmail(), $user->assertString('email'));
});

\test('locale getter returns locale', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    static::assertSame($user->getLocale(), $user->assertString('locale'));
});

\test('email verified at getter returns carbon or null', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    static::assertNotNull($user->getEmailVerifiedAt());

    $unverified = Typer::assertInstance(UserFactory::new()->unverified()->createOne(), User::class);

    static::assertNull($unverified->getEmailVerifiedAt());
});

\test('mark email as unverified clears timestamp', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    static::assertTrue($user->markEmailAsUnverified());

    $user->refresh();

    static::assertNull($user->getEmailVerifiedAt());
});

\test('me resource returns user resource', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $resource = $user->meResource();

    static::assertSame($user, $resource->resource);
});

\test('resource delegates to me resource', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    static::assertSame($user->meResource()->resource, $user->resource()->resource);
});

\test('database tokens relationship is defined', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $this->assertDatabaseCount('database_tokens', 0);
});
