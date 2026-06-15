<?php

declare(strict_types=1);

use App\Models\Store;
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

\test('provisionWarehouse creates exactly one warehouse per user', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $first = $user->provisionWarehouse();
    $second = $user->provisionWarehouse();

    \expect($first->getKey())->toBe($second->getKey());
    \expect(Store::query()->where('user_id', $user->getKey())->where('is_warehouse', true)->count())->toBe(1);
    \expect($first->getUserId())->toBe($user->getKey());
});

\test('provisionWarehouse keeps a non-warehouse store untouched', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $retail = Store::factory()->create(['user_id' => $user->getKey(), 'is_warehouse' => false]);

    $user->provisionWarehouse();

    $retail->refresh();
    \expect($retail->isWarehouse())->toBeFalse();
});
