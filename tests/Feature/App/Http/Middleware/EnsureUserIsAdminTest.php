<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest request is forwarded to the next handler (auth middleware stops it elsewhere)', function (): void {
    $middleware = new EnsureUserIsAdmin();
    $request = Request::create('/users', 'GET');

    $response = $middleware->handle($request, static fn(Request $r) => \response('OK'));

    \expect($response->getStatusCode())->toBe(302);
    \expect($response->headers->get('Location'))->toContain('/dashboard');
});

\test('limited user is redirected away from admin-only sections', function (): void {
    $store = Typer::assertInstance(
        Store::factory()->create(),
        Store::class,
    );
    $limited = Typer::assertInstance(
        UserFactory::new()->limited($store)->createOne(),
        User::class,
    );

    $middleware = new EnsureUserIsAdmin();
    $request = Request::create('/users', 'GET');
    Auth::guard('users')->setUser($limited);

    $response = $middleware->handle($request, static fn(Request $r) => \response('OK'));

    \expect($response->getStatusCode())->toBe(302);
    \expect($response->headers->get('Location'))->toContain('/dashboard');
});

\test('admin user proceeds through the middleware', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne(), User::class);

    $middleware = new EnsureUserIsAdmin();
    $request = Request::create('/users', 'GET');
    Auth::guard('users')->setUser($admin);

    $response = $middleware->handle($request, static fn(Request $r) => \response('OK'));

    \expect($response->getStatusCode())->toBe(200);
    \expect((string) $response->getContent())->toBe('OK');
});
