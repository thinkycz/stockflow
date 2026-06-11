<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Config;

class AuthShouldUseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): SymfonyResponse $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): SymfonyResponse
    {
        $config = Config::inject();

        if (\count($guards) === 0) {
            $guards = $config->authGuards();
        }

        $want = $request->headers->get('X-Auth-Guard') ?? $config->authDefaultsGuard();

        if (\in_array($want, $guards, true) === false) {
            return $next($request);
        }

        if ($want !== $config->authDefaultsGuard()) {
            $config->setAuthDefaultsGuard($want);
        }

        if ($want !== $config->authDefaultsPasswords()) {
            $config->setAuthDefaultsPasswords($want);
        }

        if ($want !== $config->authDefaultsProvider()) {
            $config->setAuthDefaultsProvider($want);
        }

        return $next($request);
    }
}
