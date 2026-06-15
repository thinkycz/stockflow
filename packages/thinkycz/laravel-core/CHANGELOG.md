# Changelog

All notable changes to `thinkycz/laravel-core` are documented here.

## [Unreleased]

### Added

- `tests/` directory with a Pest suite covering `Env`, `Config`, `Typer`,
  `Thrower`, and `Parser`.
- `README.md` describing the public API and conventions.
- `CHANGELOG.md` (this file).

## [0.1.0] — Initial release

- `Env`, `Config`, `Resolver`, `Typer`, `Thrower`, `Parser` support
  classes.
- `BaseModel`, `BaseUser` Eloquent bases.
- `BaseValidity` validation base.
- `Handler` and `ValidationException` core exceptions.
- `AutomaticController` invokable base for JSON:API.
- `ApiFormRequest` request builder.
- `AuthShouldUseMiddleware`, `SetPreferredLanguageMiddleware`,
  `SetRequestFormatMiddleware`, `ValidateAcceptHeaderMiddleware`,
  `ValidateContentTypeHeaderMiddleware` HTTP middleware.
- `Throttler`, `ThrottleSupport`, `Hash`, `Randomizer`, `SignedUrlSupport`,
  `Csv`, `Debug`, `Trans`, `Panicker`, `Limit`, `Facade` utilities.
- `lang/` and `stubs/` scaffolding.
