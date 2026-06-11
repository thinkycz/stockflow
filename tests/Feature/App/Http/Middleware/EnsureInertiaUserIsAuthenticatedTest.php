<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Typer;

\test('guest is redirected to login', function (): void {
    $middleware = new App\Http\Middleware\EnsureInertiaUserIsAuthenticated();

    $request = Request::create('/dashboard', 'GET');

    $response = $middleware->handle($request, static fn(Request $r): SymfonyResponse => new Illuminate\Http\Response('ok'));

    static::assertSame(302, $response->getStatusCode());
    static::assertStringContainsString('/login', (string) $response->headers->get('Location'));
});

\test('guest with json format throws authentication exception', function (): void {
    $middleware = new App\Http\Middleware\EnsureInertiaUserIsAuthenticated();

    $request = Request::create('/dashboard', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);

    $this->expectException(AuthenticationException::class);

    $middleware->handle($request, static fn(Request $r): SymfonyResponse => new Illuminate\Http\Response('ok'));
});

\test('authenticated user passes through', function (): void {
    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

    $this->be($user, 'users');

    $middleware = new App\Http\Middleware\EnsureInertiaUserIsAuthenticated();

    $request = Request::create('/dashboard', 'GET');

    $response = $middleware->handle($request, static fn(Request $r): SymfonyResponse => new Illuminate\Http\Response('ok'));

    static::assertSame(200, $response->getStatusCode());
    static::assertSame('ok', $response->getContent());
});
