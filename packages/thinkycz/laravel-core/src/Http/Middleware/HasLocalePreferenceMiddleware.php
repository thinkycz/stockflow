<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Models\BaseUser;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

class HasLocalePreferenceMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): SymfonyResponse $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $config = Config::inject();

        $user = null;

        foreach ($config->authGuards() as $guard) {
            $user ??= Resolver::resolveAuthManager()->guard($guard)->user();
        }

        if ($user instanceof BaseUser === false) {
            return $next($request);
        }

        $config = Config::inject();

        $locale = $user->preferredLocale();

        if ($locale !== $config->appLocale()) {
            $config->setAppLocale($locale);
        }

        if ($locale !== $request->getLocale()) {
            $request->setLocale($locale);
        }

        return $next($request);
    }
}
