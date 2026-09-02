<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Middleware\EnsureApiCookieCsrf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Http\ApiFormRequest;
use Thinkycz\LaravelCore\Routing\AutomaticController;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

final class CsrfCookieShowController extends AutomaticController
{
    /**
     * Issue a readable token for double-submit API CSRF validation.
     */
    public function __invoke(ApiFormRequest $request): SymfonyResponse
    {
        $request->builder()->validate();

        $guard = Resolver::resolveDatabaseTokenGuard(Config::inject()->assertString('auth.defaults.guard'));
        $cookies = Resolver::resolveCookieJar();

        $cookies->queue($cookies->forever(
            EnsureApiCookieCsrf::COOKIE_NAME,
            Str::random(64),
            '/',
            null,
            $guard->cookieSecure(),
            false,
            false,
            $guard->cookieSameSite(),
        ));

        return Resolver::resolveResponseFactory()->noContent();
    }
}
