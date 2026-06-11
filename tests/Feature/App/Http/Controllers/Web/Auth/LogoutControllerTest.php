<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

\test('user can logout', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $response = $this->be($user, 'users')->post('/logout');

    $response->assertRedirect('/login');
    $response->assertCookieExpired(Resolver::resolveDatabaseTokenGuard($user->getTable())->cookieName());
});
