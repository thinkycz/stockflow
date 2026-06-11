# StockFlow Guidelines

This project is an **Inertia-web** app at the UI surface, with a small
**JSON:API** surface for programmatic clients. The conventions below cover
both. Items that apply only to one surface are tagged **[Web]** or **[API]**.

## Stack and Tooling

- Makefile is the primary entry point for provisioning, formatting, and
  validation: `make local|testing|development|staging|production`, `make fix`,
  `make check`.
- Before each commit, run `make fix` then `make check`.
- PHPStan is the source of truth for type errors. Do not lower PHPStan
  levels or suppress errors in the codebase — extend the baseline only as a
  last resort, with a comment explaining why.
- Frontend type checks: `npm run type-check`; build: `npm run build`.

## Consistency

- Code must be consistent within the project and with the local
  `thinkycz/laravel-core` package.
- Follow existing project conventions for naming, imports, and
  control flow. When you find a violation, fix it before adding the new
  feature.

## Architecture Tests

`tests/Architecture/*` enforces project conventions. Reading them is
the fastest way to learn the rules. Common enforced conventions:

- Controller suffixes, model/validity/visibility naming.
- Web controllers may use `create/store` and `edit/update` pairs.
- API controllers must extend `AutomaticController` and be invokable
  with `__invoke(ApiFormRequest $request): SymfonyResponse`.
- All Enums end with `Enum` suffix and are string-backed.
- All Resources extend `JsonApiResource`.
- Only `Resolver::resolveRouteRegistrar()` may be used in `routes/*`; no
  raw `Route::*()`.
- Validity classes use `BaseValidity` and only declare wrapper helpers —
  no direct `->required()` / `->nullable()` chains.
- Models under `App\Models` extend `BaseModel` or `BaseUser`; non-User
  models must implement `querySelect()` and `scopeSearch()`.
- Strict equality only (`===`/`!==`), no `==`/`!=`.
- No `env()`, `config()`, `dd()`, `var_dump()`, `unserialize()`, etc.
  outside config files.

When in doubt, read the test that enforces the rule.

## Tests

- Every controller must have at least one feature test. 100% coverage
  of the success path is expected; error path coverage is recommended
  but not mandatory.
- Test files mirror the source tree:
  `app/Http/Controllers/Web/Auth/LoginController.php` →
  `tests/Feature/App/Http/Controllers/Web/Auth/LoginControllerTest.php`.
- Use the `createIsolatedUserWithWarehouse()` helper from `tests/Pest.php`
  to set up a user with their default warehouse store.
- Prefer factories over direct `Model::query()->create([...])` calls
  once the controller uses the factory pattern.
- The `assertInertiaFlash(TestResponse $response, string $key, mixed
$message)` helper asserts Inertia flash messages for both
  redirect and 200-OK render responses.

## HTTP Surfaces

| Surface | Path          | Returns               | Notes     |
| ------- | ------------- | --------------------- | --------- |
| Inertia | `/...`        | Inertia render or 302 | **[Web]** |
| API     | `/api/v1/...` | `JsonApiResource`     | **[API]** |

- **[Web]** Inertia controllers live in
  `app/Http/Controllers/Web/...` and return `Inertia::render(...)`,
  `RedirectResponse`, or `Response`. They use the `ValidatesWebRequests`
  trait and the core `Resolver::resolveValidator(...)` flow.
- **[API]** JSON:API controllers live in
  `app/Http/Controllers/Api/...` and extend
  `Thinkycz\LaravelCore\Routing\AutomaticController`. They use the
  `ApiFormRequest` builder and the `Typer` parser for typed input.

### **[Web]** Inertia Conventions

- Use Inertia `<Link>` for all internal navigation; never raw
  `<a href>`. Internal links that need button styling should be a
  `<Link>` whose content is a `<Button>`-styled element, or
  `as="button"` where supported.
- Use `router.get/post/...` for all form submissions and searches;
  never `window.location.href`. Preserve state on search/filter
  navigation with `router.get(url, params, { preserveState: true })`.
- Use `Inertia::flash('success', \__('...'))` for success flashes; the
  `HandleInertiaRequests` middleware also falls back to session
  `->flash()`, but `Inertia::flash()` survives 302 chains.
- For form submits, bind `@submit.prevent` and call
  `router.post(...)`; do not set redundant `method`/`action`
  attributes on the `<form>` element.
- Inertia pages live in `resources/js/pages/` and are resolved by
  `resources/js/app.ts`. Use `@/` for `resources/js` imports.
- Inertia v3 stores flash data on the dedicated
  `inertia.flash_data` session key; on a 200-OK render the message
  surfaces in the `flash.{key}` page prop.

### **[API]** JSON:API Conventions

- Resource controllers are invokable.
- The `Store` (create) action returns a generic `ModelJsonApiResource`,
  not an `Index`/`Show` resource. Updates and destroys return
  `204 No Content`.
- Output is always `application/json`.
- All persistence happens inside a transaction; the
  `AutomaticController` base handles this automatically.

## URL Format

- **[Web]** URLs in `kebab-case`: `/stock-movements`, `/items`,
  `/stores`. Routes use the resource id as a path segment:
  `/items/{item}`. This is an intentional exception to the API rule
  below.
- **[API]** URLs in `snake_case` plural: `/api/v1/email_verification/resend`.

## Flat Endpoints (API only)

- **[API]** Endpoints are flat with no nested relations.
    - Right: `GET /api/v1/notifications/index?filter[user_id]=1`
    - Wrong: `GET /api/v1/users/1/notifications`
- **[API]** Don't use route params, use query params:
  `GET /api/v1/users/show?id=1`.
- **[API]** Only `GET` and `POST` methods. Replace `PUT`/`PATCH`/`DELETE`
  with `POST` and a postfix: `POST /api/v1/users/update?id=1`.

## Basic Endpoint Structure (API only)

- Index: `GET /api/v1/{models}/index`
- Show: `GET /api/v1/{models}/show?id=number`
- Store: `POST /api/v1/{models}/store`
- Update: `POST /api/v1/{models}/update?id=number`
- Destroy: `POST /api/v1/{models}/destroy?id=number`
- Attach/Detach: `POST /api/v1/{a_model_b_model}/store|destroy`

## Naming Conventions

- Classes have type suffixes: `Controller`, `Request`, `Resource`,
  `Service`, `Validity`.
- Models exclude `Model` suffix (`Item.php`, not `ItemModel.php`).
- Traits have `Trait` suffix. Interfaces have `Interface` suffix.
  Abstract classes have `Abstract` prefix. Final classes have `Final`
  prefix. Enums have `Enum` suffix.
- Class names are singular: `UserStoreController.php`,
  `UserRoleEnum.php`.

## Tables, Columns, Foreign Keys

- Tables plural `snake_case`. Pivot tables singular alphabetical
  `snake_case`. Columns `snake_case`. Foreign keys end in `_id`.

## PSR Standards

- Follow PSR-1, PSR-2, PSR-4, PSR-12, PSR-5, PSR-19.

## Translation Files

- `resources/js/i18n/{en,cs,sk}.json` for frontend (vue-i18n).
- `lang/{en,cs,sk}.json` for backend (`__()`) — includes email subjects
  and one-off strings.
- Three locales must remain in sync. Add a key to all three at the
  same time.
- Keys in `snake_case` (nested OK).
- Hardcoded user-facing strings in PHP or Vue are a code smell — move
  them to i18n.

## Controllers, Authorization, Validation

- Authorization and validation in the controller, not in request
  classes.
- **[Web]** Validation rules live in `App\Http\Validity\*Validity`
  classes; inject with `*Validity::inject($user->getKey())` (or
  `$model->getUserId()` for edit flows) and use
  `$this->validateRequest($request, $rules)` to obtain a typed
  `Parser` for `assertString/assertNullableInt/...`.
- **[API]** Validation rules live in
  `App\Http\Validation\*Validity` classes too; the API controller
  passes them to the `ApiFormRequest::builder()`.
- Multi-step writes (`create + related`, `update + tokens`,
  `password + revoke`) must run in `DB::transaction(...)`.
- All queries must be scoped to the logged-in user via the
  `BelongsToUser` trait's `->forUser($user)` scope (or a relation
  through an already-scoped parent).
- Forbids `ValidationException::withMessages(...)`. Use
  `Thrower::default()->message($key, $message)->throw()`.
- Forbids `env()`, `config()`, `dd()`, `var_dump()`, `print_r()`,
  `unserialize()`, `extract()` in `app/`. `app()->environment()`,
  `Auth::shouldUse()`, `Password::getConfig()`, etc. are also
  forbidden; use the core's typed helpers instead.

## Dependency Injection

- **[Web]** Don't use constructor or method DI. Resolve via
  `Resolver::resolve*()` in the method body.
- **[API]** `AutomaticController` injects via constructor; services
  used inside action methods can be resolved via `Resolver` or method
  DI.

## Contracts

- Imported classes from `Illuminate\Contracts` should have a `Contract`
  suffix: `AuthenticatableContract`. (This is a soft preference for new
  code; the core package itself doesn't always follow it.)

## Down Migration

- Skip `down()` in migrations.

## Comments

- Every property and every method must have a docblock comment.
- Do not use phpdocblocks that affect functionality at runtime
  (opcache strips them).

## Throttling

- Throttle at the controller level after validation. Use the
  `ThrottlesWebRequests` trait for the web surface.
- Failed validation must not increase throttle hits.

## ID Getter

- Access model IDs only via `getKey()`, `getAuthIdentifier()`,
  `getRouteKey()`. Never read `$model->id` directly.

## Mail Queue

- Emails and notifications must implement `ShouldQueue` and be sent
  after the database transaction commits.

## Working with Logged-in User

- Use `Model::mustAuth(): User` (throws 401) and
  `Model::auth(): User|null` from the core's base models.

## Inheritdoc

- Overridden methods must inherit phpdoc with `@inheritDoc`.

## Cron Schedule

- Define cron tasks using Jobs, not Artisan commands.

## Single Job

- Jobs processing collections must use recursive processing: fetch
  the model, dispatch a single-model job for each within a transaction.

## CRUD Command

- Generate boilerplate code exclusively with CRUD scaffolding commands
  shipped by the `thinkycz/laravel-core` package.
