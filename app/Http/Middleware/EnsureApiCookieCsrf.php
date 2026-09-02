<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

final class EnsureApiCookieCsrf
{
    public const COOKIE_NAME = 'XSRF-TOKEN';

    /**
     * Require double-submit CSRF validation for cookie-authenticated mutations.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->bearerToken() !== null || !$this->hasValidAuthenticationCookie($request)) {
            return $next($request);
        }

        $cookieToken = $request->cookies->get(self::COOKIE_NAME);
        $headerToken = $request->header('X-XSRF-TOKEN');

        if (!\is_string($cookieToken) || !\is_string($headerToken) || !\hash_equals($cookieToken, $headerToken)) {
            throw new TokenMismatchException();
        }

        return $next($request);
    }

    /**
     * Determine whether a configured guard authenticates through its cookie.
     */
    private function hasValidAuthenticationCookie(Request $request): bool
    {
        $config = Config::inject();

        foreach ($config->authGuards() as $guard) {
            if ($config->assertNullableString("auth.guards.{$guard}.driver") !== 'database_token') {
                continue;
            }

            $databaseTokenGuard = Resolver::resolveDatabaseTokenGuard($guard);
            if ($request->cookies->has($databaseTokenGuard->cookieName()) && $databaseTokenGuard->user() !== null) {
                return true;
            }
        }

        return false;
    }
}
