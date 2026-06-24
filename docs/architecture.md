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

### Inventory counts and branch statistics

`/inventory-counts` is a per-store editor that lets staff type the on-hand
quantity of every catalog item; saving the form writes a snapshot row to
`inventory_counts` (preserving the history of physical counts) and
upserts the matching `store_items` row, so the single source of truth for
"what is on the shelf right now" stays on `store_items`. The page also
renders a 30-day per-item sparkline (inline SVG via
`resources/js/components/ui/Sparkline.vue`, no chart library) so the
operator can read the trend at a glance.

`/inventory-counts/history` is the audit view: a paginated list of every
`inventory_counts` snapshot with filters for store, item, and date range
(default window 90 days). The page is accessible to both the main admin
and limited users; limited users are pinned to their assigned store, and
visitors without an `assigned_store_id` are refused (403).

`/reports/statistics` aggregates three data sources for the selected
branch over a configurable window (default 30 days):

- `StatementDay` rows for revenue, channel breakdown, and daily totals.
- `StockMovement` rows for incoming (received) and outgoing (consumed /
  transferred) volume and value.
- `store_items` joined with `items` for the current inventory value.

The same `/inventory-counts` page computes per-item average daily
consumption from outgoing movements in the window and predicts when the
branch will run out, so the operator can plan restocking.

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

## Role-based access control

There is exactly one **main admin** per deployment, seeded as
`test@test.com` (`is_admin = true`, `parent_user_id = null`). The admin
provisions **limited users** (`is_admin = false`,
`parent_user_id = admin.id`, `assigned_store_id = one-of-admin-stores`)
from the `/users` section.

- Limited users are pinned to one store and only see Dashboard, Výkazy
  (Statements), Inventura, and Settings in `AppLayout.vue`. The store
  select on `/statements` and `/inventory-counts` is fixed; cross-store
  access returns 403.
- All other routes (`/items`, `/stock-movements`, `/stores`, `/reports`,
  `/users`) are wrapped by the `EnsureUserIsAdmin` middleware
  (alias `admin`) which redirects to the dashboard with an Inertia flash
  when the visitor is not the main admin.
- `User::scopeForAdmin(Builder, User $admin)` returns the admin plus
  their subordinate users for listing pages, and
  `User::scopeForAssignedStore(Builder, Store $store)` returns the
  limited user pinned to a given store.
- Limited-user data is scoped to the parent admin: `Statement*` and
  `InventoryCount*` controllers resolve stores, items, and snapshots
  through the parent admin, so a limited user only ever sees and writes
  to their assigned store while the admin keeps a single owner of the
  underlying data.

## Inventory history

`InventoryCount` rows are an append-only audit log of physical counts.
The `/inventory-counts/history` page lists every snapshot with store /
item / date-range filters (default window 90 days) and Czech-formatted
timestamps. The `/inventory-counts` index additionally renders a
30-day sparkline (`resources/js/components/ui/Sparkline.vue`, pure SVG)
next to each catalog item, giving the operator a quick visual trend.
`InventoryCountService::historyForUser` and `::sparklineForItem` build
the per-row and per-user view models.

## Date formatting

All UI dates use the `useCzechDate()` composable
(`resources/js/composables/useCzechDate.ts`) and are rendered in
`dd.MM.yyyy` (or `dd.MM.yyyy HH:mm` for timestamps) regardless of the
active UI locale. The backend always returns ISO 8601 strings; the
frontend formats on the client. `resources/js/lib/format.ts` also uses
`Intl.DateTimeFormat('cs-CZ', …)` so legacy call sites stay consistent.
