<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Typer;

class SetPreferredLanguageMiddleware
{
    /**
     * Handle an incoming request.
     *
     * The authenticated user's stored `locale` always wins over the
     * browser's `Accept-Language` header; that way, after a Settings
     * page POST that writes the new locale, the very next request
     * already renders in the new language. Unauthenticated visitors
     * (and visitors whose stored locale is not in the configured
     * set) fall back to the `Accept-Language` negotiation.
     *
     * @param Closure(Request): SymfonyResponse $next
     */
    public function handle(Request $request, Closure $next, string ...$locales): SymfonyResponse
    {
        $config = Config::inject();

        if (\count($locales) === 0) {
            $locales = $config->appLocales();
        }

        /** @var array<int, string> $allowed */
        $allowed = \array_values(\array_map('strval', $locales));

        $user = $request->user();
        $stored = $user instanceof Authenticatable ? Typer::assertNullableString($user->getAttribute('locale')) : null;

        $locale = $stored !== null
            ? $this->matchLocale($stored, $allowed)
            : $request->getPreferredLanguage($allowed);

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

    /**
     * Resolve the user's stored locale against the configured set.
     *
     * @param array<int, string> $locales
     */
    private function matchLocale(string $stored, array $locales): string|null
    {
        if (\in_array($stored, $locales, true)) {
            return $stored;
        }

        foreach ($locales as $configured) {
            if (\str_starts_with($stored, $configured . '_')) {
                return $configured;
            }
        }

        return null;
    }
}
