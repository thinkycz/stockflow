# thinkycz/laravel-core

A small set of opinionated Laravel helpers used by
[`thinkycz/laravel-inertia-stack`](https://thinky.cz) and its derivatives.
The package stays intentionally narrow: it provides the framework shims
that keep app-level code thin (validators, resolvers, parsers, throwers,
typed env readers) and nothing more.

## Requirements

- PHP 8.3+
- Laravel 13.x

## Installation

The package is consumed via a Composer path repository in the host
project. There is nothing to install or wire up manually; Laravel's
auto-discovery registers `CoreServiceProvider` from the
`laravel.providers` extra.

## Public API

| Class | Purpose |
| --- | --- |
| `Thinkycz\LaravelCore\Support\Env` | Typed environment reader. Use `Env::inject()->parseXxx('VAR')` instead of calling `env()` directly. |
| `Thinkycz\LaravelCore\Support\Config` | Typed config reader. Wraps `config()` with `assertString` / `parseNullableInt` / etc. |
| `Thinkycz\LaravelCore\Support\Resolver` | Lazy singletons for framework services (`app`, `validator`, `redirector`, `route registrar`, ...). |
| `Thinkycz\LaravelCore\Support\Typer` | Strict runtime assertions for `mixed` inputs (`assertString`, `assertInt`, `assertInstance`, `parseNullableFloat`, ...). |
| `Thinkycz\LaravelCore\Support\Thrower` | Fluent builder for `ValidationException`. Lets you attach per-field messages before calling `throw()`. |
| `Thinkycz\LaravelCore\Support\Parser` | Read-only accessor for `$request->validate()` output, with the same `assertString` / `parseBool` surface as `Typer`. |
| `Thinkycz\LaravelCore\Models\BaseModel` | Eloquent base with relation-getter conventions (`getStore()`, `getMovementItems()`) and assertion helpers. |
| `Thinkycz\LaravelCore\Models\BaseUser` | Authenticatable base used by `App\Models\User`. |
| `Thinkycz\LaravelCore\Validation\BaseValidity` | Validity base for `App\Http\Validation\*Validity` classes. |
| `Thinkycz\LaravelCore\Exceptions\Handler` | Core exception handler installed in `bootstrap/app.php` via `withSingletons`. |
| `Thinkycz\LaravelCore\Exceptions\ValidationException` | Validation exception with the same message-bag semantics as Laravel's, used by `Thrower`. |
| `Thinkycz\LaravelCore\Routing\AutomaticController` | Invokable base for JSON:API controllers. |
| `Thinkycz\LaravelCore\Http\ApiFormRequest` | Request builder for JSON:API controllers. |
| `Thinkycz\LaravelCore\Http\Middleware\AuthShouldUseMiddleware` | Switches the active auth guard per request. |
| `Thinkycz\LaravelCore\Http\Middleware\SetPreferredLanguageMiddleware` | Picks a locale from the user or `Accept-Language` header. |
| `Thinkycz\LaravelCore\Http\Middleware\SetRequestFormatMiddleware` | Locks the request format to JSON for API routes. |
| `Thinkycz\LaravelCore\Http\Middleware\ValidateAcceptHeaderMiddleware` | Rejects unsupported `Accept` values on the API surface. |
| `Thinkycz\LaravelCore\Http\Middleware\ValidateContentTypeHeaderMiddleware` | Rejects unsupported `Content-Type` values on the API surface. |
| `Thinkycz\LaravelCore\Support\Throttler` / `ThrottleSupport` | Throttle helpers for the web surface. |
| `Thinkycz\LaravelCore\Support\Hash` | Hasher resolver. |
| `Thinkycz\LaravelCore\Support\Randomizer` | Secure random utilities. |
| `Thinkycz\LaravelCore\Support\SignedUrlSupport` | Signed-URL helpers. |
| `Thinkycz\LaravelCore\Support\Csv` | CSV encoder/decoder. |
| `Thinkycz\LaravelCore\Support\Debug` | Debug helpers (dump / dd facade). |
| `Thinkycz\LaravelCore\Support\Trans` | Translation helpers. |
| `Thinkycz\LaravelCore\Support\Panicker` | Panic helper that throws a `RuntimeException` with file/line info on assertion failure. |
| `Thinkycz\LaravelCore\Support\Limit` | Rate-limit value object. |
| `Thinkycz\LaravelCore\Support\Facade` | Base facade class. |

## Conventions

- All app-level behaviour should stay thin and delegate to the helpers
  above. See `docs/guidelines.md` in the host project for the rules
  the package's API surface is designed to support.
- `env()` is forbidden in `app/` and discouraged everywhere else; use
  `Env::inject()->parseXxx()` instead. `Config` is the typed reader for
  `config('...')` values.
- Eloquent models in the app extend `BaseModel` (or `BaseUser` for
  `App\Models\User`) and read persisted attributes through explicit
  getters that use `assertString` / `assertInt` / `assertNullableString`
  / `Typer::*`.

## Testing

```bash
composer install
composer test
```

The package ships with a Pest suite covering the pure-PHP utilities
(`Env`, `Config`, `Typer`, `Thrower`, `Parser`). Laravel-touching
helpers are exercised by the host project's feature tests.

## License

Proprietary. © Thinkycz.
