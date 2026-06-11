<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Config;

class SetPreferredLanguageMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): SymfonyResponse $next
     */
    public function handle(Request $request, Closure $next, string ...$locales): SymfonyResponse
    {
        $config = Config::inject();

        if (\count($locales) === 0) {
            $locales = $config->appLocales();
        }

        $locale = $request->getPreferredLanguage($locales);

        if ($locale === null) {
            return $next($request);
        }

        if ($locale !== $config->appLocale()) {
            $config->setAppLocale($locale);
        }

        if ($locale !== $request->getLocale()) {
            $request->setLocale($locale);
        }

        return $next($request);
    }
}
