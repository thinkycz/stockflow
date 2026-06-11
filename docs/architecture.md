# Architecture

## High-level

This project is a Laravel 13 + Inertia 3 + Vue 3 inventory starter with
**per-user data isolation** within one deployment. Each authenticated user
owns their own stores, item catalog, and stock movements. Quantity is tracked
per store via `store_items`. Users can mark any store as a **warehouse** (`is_warehouse`);
a default Warehouse is auto-created on registration. On the create form, movement
type is **inferred** from source and destination: no source + destination =
incoming (receipt/purchase); source + different destination = outgoing (warehouse
source displays as dispatch, retail source as store transfer). Manual adjustment
is a separate explicit mode (`?mode=adjustment`) that posts `type: adjustment`.
Stock can move between any owned stores; quantity is checked at the source store
for outgoing transfers.

The backend ships with two HTTP surfaces and one framework helper package; the
frontend is a Vite-built Vue 3 app that consumes Inertia pages from the
backend.

```mermaid
flowchart LR
    Browser -->|HTTP| Laravel
    subgraph Laravel
      Web[Web routes<br/>app/Http/Controllers/Web]
      Api[Api routes<br/>app/Http/Controllers/Api]
      Core[packages/thinkycz/laravel-core]
      Web --> Core
      Api --> Core
    end
    Laravel -->|Inertia JSON| Browser
    Browser -->|Vite assets| Vite
    Vite -->|bundles| Browser
```

## Middleware chain (web)

```mermaid
flowchart TD
    Req[Request] --> TrustProxies
    TrustProxies --> EncryptCookies
    EncryptCookies --> AddQueuedCookies
    AddQueuedCookies --> StartSession
    StartSession --> ShareErrorsFromSession
    ShareErrorsFromSession --> VerifyCsrfToken
    VerifyCsrfToken --> SubstituteBindings
    SubstituteBindings --> AuthShouldUse[AuthShouldUseMiddleware]
    AuthShouldUse --> SetPreferredLanguage[SetPreferredLanguageMiddleware]
    SetPreferredLanguage --> InertiaShare[HandleInertiaRequests]
    InertiaShare --> GuestOrAuth{guest:users?}
    GuestOrAuth -->|guest| Controller
    GuestOrAuth -->|auth| Redirect
    Controller --> Resp[Inertia Response]
```

`AuthShouldUseMiddleware` and `SetPreferredLanguageMiddleware` come from
`packages/thinkycz/laravel-core`. `HandleInertiaRequests` (in
`app/Http/Middleware/`) extends Inertia's base middleware to share `app`,
`auth`, `flash`, and inherited `errors`.

## Validation-error flow (Inertia v3)

```mermaid
sequenceDiagram
    participant FE as Vue page
    participant L as Laravel
    participant H as Exception handler
    participant IM as Inertia middleware

    FE->>L: POST /login (X-Inertia: true)
    L->>H: throws ValidationException
    H->>L: Inertia::render(prev component, {errors})<br/>status 422
    L-->>FE: 422 + page JSON (errors in props)
    FE->>FE: useForm onError() → form.setError(errors)
    FE-->>User: FieldError renders
```

Inertia v3 does **not** auto-follow a bare 302 redirect on POST. The handler
in `bootstrap/app.php` therefore re-renders the previous Inertia component
with status 422 and the `errors` prop, so the Vue client merges errors into
the page and populates `useForm().errors`.

## Authentication

```mermaid
flowchart LR
    subgraph Login
      C[LoginController::store] --> H[Resolver::resolveHasher]
      C --> DT[DatabaseTokenGuard]
    end
    DT -->|set cookie| Browser
    Browser -->|subsequent requests| MW[EnsureInertiaUserIsAuthenticated<br/>or guest:users]
    MW --> Controller
```

- Cookie is HTTP-only and named via the `database_token` config.
- The guard stores `(user_id, token_hash, expires_at)` in the
  `database_tokens` table.
- `LogoutController::destroy` revokes the token row via
  `$user->databaseTokens()->getQuery()->delete()` before invalidating the
  session.

## Frontend layout

```
resources/js/
├── app.ts                  # Inertia app bootstrap
├── bootstrap.ts            # Axios + CSRF setup
├── components/
│   └── ui/                 # FieldError, FlashAlerts, Select, Input, Button
├── composables/
│   └── useSharedProps.ts   # typed accessor for shared props
├── layouts/
│   ├── AppLayout.vue       # authenticated shell
│   └── AuthLayout.vue      # guest shell
├── lib/                    # framework-agnostic helpers
├── pages/                  # Inertia page components
└── types/
    └── index.ts            # AuthUser, AppMeta, FlashProps, SharedProps
```

Pages import shared props via `useSharedProps()` and render them with the
`ui/` primitives. Forms use `@inertiajs/vue3`'s `useForm()` for typed
client-side state; validation errors arrive via page props after the 422
handshake above.

## Local packages

- `packages/thinkycz/laravel-core/` — the framework helper. Provides
  `Resolver`, `Config`, `Env`, `Typer`, `AuthValidity`, `Thrower`, `Parser`,
  `DatabaseToken`, `EmailBrokerService`, `AuthShouldUseMiddleware`,
  `SetPreferredLanguageMiddleware`, and the
  `Illuminate\Contracts\Debug\ExceptionHandler` binding.

App-level code should not re-implement what core already exposes. Use core
helpers before introducing new ones.

## Storage

- Sessions: file driver in dev, configurable in `config/session.php`. E2e
  dev server runs with `SESSION_SECURE_COOKIE=false` and `APP_ENV=testing`.
- Cache: `array` in tests, `file` in dev, `redis` in production
  (per `config/cache.php`).
- Database: MySQL 8 in production; SQLite `:memory:` in tests.

## Runtime services

MySQL 8, Redis, cron, and supervisor are the production runtime services
declared in `composer.json` / `docker-compose.yml` (when present).

## Internationalization (i18n)

The backend (`lang/*.json`) and frontend (`resources/js/i18n/*.json`) translation files are separate but mirrored. This duplication is a deliberate design tradeoff to keep the frontend independent of API calls for localizing core UI shells during bootstrap. In the long term, they can be consolidated by either exposing a backend localization API endpoint or generating the client JSON files from the server JSON files during a build step.
