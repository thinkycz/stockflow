<?php

declare(strict_types=1);

use App\Enums\LimitedUserSectionEnum;
use App\Http\Middleware\EnsureLimitedUserCanAccessSection;
use App\Models\Store;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Thinkycz\LaravelCore\Support\Typer;

\test('limited user proceeds when the requested section is enabled', function (): void {
    $store = Typer::assertInstance(Store::factory()->create(), Store::class);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne(), User::class);
    Auth::guard('users')->setUser($limited);

    $response = (new EnsureLimitedUserCanAccessSection())->handle(
        Request::create('/shifts', 'GET'),
        static fn(Request $request) => \response('OK'),
        LimitedUserSectionEnum::SHIFTS->value,
    );

    \expect($response->getStatusCode())->toBe(200);
});

\test('disabled Inertia section redirects to dashboard', function (): void {
    $store = Typer::assertInstance(Store::factory()->create(), Store::class);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne([
        'disabled_sections' => [LimitedUserSectionEnum::SHIFTS->value],
    ]), User::class);
    Auth::guard('users')->setUser($limited);

    $response = (new EnsureLimitedUserCanAccessSection())->handle(
        Request::create('/shifts', 'GET'),
        static fn(Request $request) => \response('OK'),
        LimitedUserSectionEnum::SHIFTS->value,
    );

    \expect($response->getStatusCode())->toBe(302)
        ->and($response->headers->get('Location'))->toContain('/dashboard');
});

\test('disabled JSON section returns forbidden', function (): void {
    $store = Typer::assertInstance(Store::factory()->create(), Store::class);
    $limited = Typer::assertInstance(UserFactory::new()->limited($store)->createOne([
        'disabled_sections' => [LimitedUserSectionEnum::RECIPES->value],
    ]), User::class);
    Auth::guard('users')->setUser($limited);
    $request = Request::create('/recipes', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);

    \expect(fn() => (new EnsureLimitedUserCanAccessSection())->handle(
        $request,
        static fn(Request $request) => \response('OK'),
        LimitedUserSectionEnum::RECIPES->value,
    ))->toThrow(HttpException::class);
});

\test('admin always proceeds through section access middleware', function (): void {
    $admin = Typer::assertInstance(UserFactory::new()->admin()->createOne([
        'disabled_sections' => LimitedUserSectionEnum::values(),
    ]), User::class);
    Auth::guard('users')->setUser($admin);

    $response = (new EnsureLimitedUserCanAccessSection())->handle(
        Request::create('/shifts', 'GET'),
        static fn(Request $request) => \response('OK'),
        LimitedUserSectionEnum::SHIFTS->value,
    );

    \expect($response->getStatusCode())->toBe(200);
});
