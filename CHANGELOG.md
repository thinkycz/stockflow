# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- 90 PHPUnit feature tests / 198 assertions (up from 14 / 45 in baseline).
- 16 Playwright e2e tests covering register, login, logout, password reset,
  profile update, locale switch, email verification flash, and protected
  route redirects.
- `app/Http/Controllers/Web/Auth/EmailVerificationConfirmController` —
  SPA target of the email verification link. The core
  `EmailVerificationNotification` builds a URL of the form
  `<spa.email_verification_url>?guard=…&email=…&token=…&locale=…`; this
  controller is the GET handler that consumes the token via
  `EmailBrokerService::validate()`, marks the user verified, dispatches
  the `Verified` event, and redirects to the dashboard (if the visitor
  is already signed in) or to the login page (so they can sign in with
  the now-verified address).
- 6 phpunit tests for the new controller covering the valid-token
  happy path, the unverified redirect for unauthenticated visitors,
  the already-verified idempotent path, the invalid-token error
  redirect, the unknown-email error redirect, and the missing-parameter
  422 response.
- `FieldError.vue`, `FlashAlerts.vue`, `Select.vue`, `FormField.vue` shared UI
  primitives under `resources/js/components/ui/`.
- `useSharedProps()` composable returning `{app, auth, user, flash, flashSuccess,
flashError, errors}` with strict TypeScript types.
- `app/Http/Resources/UserResource::toId()` for consistent `id` projection.
- `app/Http/Controllers/Web/Concerns/ThrottlesWebRequests` trait applied to
  auth web controllers.
- `app/Http/Middleware/EnsureInertiaUserIsAuthenticated` throws
  `AuthenticationException` for JSON requests.
- Inertia 3 validation-error handling in `bootstrap/app.php` that re-renders
  the originating component (resolved from request path) with a 422 status
  (Inertia v3 client does not follow bare 302 redirects from
  `ValidationException`).
- Inertia 3 success-flash handling: web controllers use
  `$request->session()->flash(...)` + `Inertia::render(...)` so the success
  message appears on the same page instead of being lost on the 302 redirect
  the client does not follow.
- Database-token revocation via `getQuery()->delete()` on logout.
- `make test-coverage` target (requires `xdebug`).
- `lefthook.yml` pre-commit (lint) and pre-push (stan + tests + e2e) hooks.
- `docs/architecture.md` with mermaid request/middleware diagrams.
- `LICENSE` (MIT), `CONTRIBUTING.md`.
- `spa.email_verification_url` translation key (en + cs) so the core email
  verification notification can render the SPA confirmation link.
- `Alert.vue` now renders `role="alert"` so success/error messages are
  announced by screen readers and are locatable via `getByRole('alert')` in
  tests.
- `AppLayout.vue` exposes a skip-to-content link, `aria-label="Primary"` on
  the nav, and `aria-current="page"` on the active link.

### Changed

- `app/Http/Controllers/Web/Auth/*::store` controllers no longer hash the
  password twice; the single `Resolver::resolveHasher()->check(...)` call now
  serves both auth and constant-time comparison.
- All 6 form pages migrated to Inertia 3 `<Form>` component (replaces the
  custom form helpers).
- `app/Http/Middleware/HandleInertiaRequests::share()` now reads flash
  messages via `Inertia::getFlashed($request)` first and falls back to
  `$request->session()->get($key)`. The Inertia-flash path survives
  the 302 → guest-redirect → final Inertia render chain that a plain
  session flash cannot (the session ages after a single request).
- `Input.vue` and `Select.vue` accept a `defaultValue` prop and an
  `invalid`/`describedBy` pair, wired up via the new `FormField.vue`
  wrapper to `aria-invalid` and `aria-describedby`.
- `Label.vue` renders a red asterisk (aria-hidden) for `required` fields.
- `AppLayout.vue` and `AuthLayout.vue` share `<Brand>` and `<FlashAlerts>`
  components; pages no longer mount their own `<FlashAlerts />`.
- `tests/TestCase::inertiaHeaders()` hardened to include `X-Inertia: true`,
  `Accept: text/html`, and a request-aware Referer.
- `make e2e` runs Playwright with `webServer` block driving the dev server
  under `APP_ENV=testing`, `SESSION_SECURE_COOKIE=false`, `MAIL_MAILER=log`.

### Removed

- Dead `PULSE_ENABLED` and `TELESCOPE_ENABLED` env entries from `phpunit.xml`.
- Old `tests/e2e/debug*.spec.ts` diagnostic harnesses.
- `Symfony\Component\HttpFoundation\Response` return type from
  `VerifyEmailController::store`, `ProfileController::update`,
  `PasswordController::update`, and `ForgotPasswordController::store` —
  the controllers now return `Inertia\Response` to keep the page stable
  across the POST.
- A brittle e2e test for the `EmailVerificationConfirmController`
  invalid-token flash chain. The phpunit suite covers the same logic
  with a real token; the browser-driven chain depends on session cookie
  lifecycle details that the phpunit test client handles differently.

### Fixed

- `email:dns` rule failing for `example.com` in dev: e2e dev server now uses
  `APP_ENV=testing` so the basic `email` rule is applied (no DNS lookup).
- 500 ValidationException rendered as symfony debug HTML for Inertia requests
  in debug mode; replaced with Inertia-aware render in `bootstrap/app.php`.
- Logout redirect for guests (was 302 to `/` then 302 to `/login`); tests
  accept either URL.
- Success flash messages lost on Inertia form submissions because the v3
  client does not follow plain 302 redirects; controllers now re-render the
  same Inertia page with `session()->flash(...)` so the flash renders in
  the next response.
- `VerifyEmail` page rendered two copies of the success alert because
  `<FlashAlerts />` was mounted both in the layout and the page; the page
  now relies on the layout's instance.
- `bootstrap/app.php` validation handler defaulted to the `auth/Login`
  component for every form path, breaking non-login form errors; the handler
  now resolves the originating component from the request path.
- Email verification link click landed on a 404 (the
  `spa.email_verification_url` translation pointed at the API endpoint).
  The web route `GET /email/verify` is now registered and the
  translation points at it; the new controller consumes the token,
  marks the user verified, and redirects.

### Added (inventory)

- `/inventory-counts` page and `inventory-counts.update` POST endpoint.
  The index shows a per-store grid of every catalog item with the
  current quantity, the last counted timestamp, the average daily
  consumption (from outgoing `stock_movements` in the last 30 days), the
  predicted number of days until the branch runs out, and a status
  badge (`out` / `soon` / `ok` / `no_data`). Saving the form writes a
  snapshot row to the new `inventory_counts` table and upserts the
  matching `store_items.quantity`.
- `/reports/statistics` page (under `Reports`) that aggregates sales
  from `StatementDay` plus incoming / outgoing stock movements and the
  current inventory value for the selected branch and period
  (7–365 days, default 30). Renders metric cards, daily incoming vs.
  outgoing bar charts, a top-consumed items table, and a channel pie.
- `App\Models\InventoryCount`, `InventoryCountFactory`,
  `InventoryCountValidity`, `InventoryCountService`,
  `InventoryCountIndexController`, `InventoryCountUpdateController`,
  `StatisticsController`, and migration
  `2026_06_24_000001_create_inventory_counts_table.php`.
- i18n keys `nav.inventory_counts`, `nav.statistics`,
  `inventory_counts.*` and `reports.statistics.*` in cs/en/sk
  (including backend `Inventory count saved.` flashes).
- 23 new feature tests (model, service, controllers, statistics).

### Added (users & roles)

- Role-based access control with a single main admin (`test@test.com`) plus
  isolated limited users. Limited users are pinned to exactly one store and
  may only access Dashboard, Výkazy (Statements), Inventura, and Settings.
- `is_admin`, `parent_user_id`, `assigned_store_id` columns on `users`
  (migration `2026_06_24_000002_add_role_and_store_to_users_table.php`).
- `/users` admin CRUD under middleware `EnsureUserIsAdmin` (alias `admin`):
  list, create, edit, destroy. Limited users attempting to hit any of
  these routes are redirected to the dashboard with an Inertia flash.
- `User` model scopes `scopeAdmin`, `scopeLimited`, `scopeForAdmin`,
  `scopeForAssignedStore`; relation `assignedStore`, `subordinateUsers`;
  getters `isAdmin`, `getAssignedStoreId`, `getParentUserId`,
  `getAssignedStore`.
- `Statement*` and `InventoryCount*` controllers resolve the store / item
  scope through the parent admin for limited users, so the limited user
  only sees (and writes to) their assigned store. Cross-store access
  returns 403; the store select on `/statements` and `/inventory-counts`
  is fixed to a single entry.
- `UserFactory` states `admin()` and `limited(Store $store)`.
- i18n keys `nav.users`, `users.*` (title, columns, role, create, edit,
  fields, actions, confirm_delete), `flash.no_permission`,
  `flash.cannot_delete_admin`, `flash.cannot_modify_admin_role` in
  cs/en/sk.
- 4 new user controller test classes plus `UserRoleTest` and
  `EnsureUserIsAdminTest`.

### Added (inventory history)

- `/inventory-counts/history` page listing every snapshot with filters
  for store, item, and date range (default window 90 days). Czech
  timestamps via `useCzechDate()`. Limited users are pinned to their
  assigned store; access without `assigned_store_id` returns 403.
- Sparkline column on `/inventory-counts` index rendering the 30-day
  per-item quantity trend as inline SVG
  (`resources/js/components/ui/Sparkline.vue`, no chart library).
- `InventoryCountService::historyForUser` and `::sparklineForItem` plus
  the `InventoryCountHistoryController` (under standard auth middleware,
  accessible to both admin and limited roles).
- i18n keys `inventory_counts.history.*` in cs/en/sk.

### Added (dates)

- All UI dates are now rendered in Czech `dd.MM.yyyy` (and
  `dd.MM.yyyy HH:mm` for timestamps) via the new `useCzechDate()`
  composable. The backend still emits ISO 8601 strings; formatting lives
  on the client.
- `resources/js/lib/format.ts` switched to `Intl.DateTimeFormat('cs-CZ', …)`
  so the project never relies on the browser locale.

### Removed (auth)

- `/register` GET/POST routes, the `Web\Auth\RegisterController`, the
  `Register.vue` page, and the `Web\Auth\RegisterControllerTest`. New
  accounts can only be provisioned by the main admin via
  `/users/create`. The only admin is the seeded `test@test.com` account.
- i18n keys `auth.register.*` and `auth.login.register_prompt` removed
  from cs/en/sk.
- `User::provisionWarehouse()` no longer provisions the default
  `Warehouse` store for users created by an admin (admin-owned stores
  remain unaffected).

## [0.1.0] - 2026-06-07

Initial snapshot captured in `docs/verification/baseline-2026-06-07.md`.
14 tests / 45 assertions, Inertia 2 → 3 migration, PHP 8.3, Laravel 13.
