# Application documentation

## Tech stack

- PHP 8.3
- Laravel 13
- Inertia 3
- Vue 3 with TypeScript
- Tailwind 4
- Composer 2
- Node 22 or newer recommended for Vite tooling

## Packages

| package                              | description                         |
| ------------------------------------ | ----------------------------------- |
| `thinkycz/laravel-core`              | internal Laravel core package       |
| `inertiajs/inertia-laravel`          | Laravel server adapter for Inertia  |
| `laravel/slack-notification-channel` | queued operational Slack messages   |
| `@inertiajs/vue3`                    | Vue client adapter for Inertia      |
| `@inertiajs/vite`                    | Inertia Vite integration            |
| `vue`                                | frontend framework                  |
| `tailwindcss`                        | styling system                      |
| `class-variance-authority/clsx`      | shadcn-vue-compatible class helpers |

## Runtime services

- MySQL 8 for persistent local/deployed environments.
- Redis for cache/session/queue in non-local environments.
- Cron for Laravel scheduler.
- Supervisor for queue workers.

## HTTP surfaces

- Inertia web app:
    - `/login`, `/forgot-password`, `/reset-password`
    - `/dashboard`
    - `/inventory`, `/stock-movements`, `/stores`
    - `/statements` (POST `/statements/{statement}` and `/statements/{statement}/clear`)
    - `/inventory-counts` (POST `/inventory-counts` to persist a new session)
    - `/inventory-counts/history` (admin + limited, default 90-day window)
    - `/inventory-counts/{session}` (read-only session detail)
    - `/reports`, `/reports/statistics`
    - `/users` admin CRUD (GET index, GET `/create`, POST store, GET `/users/{id}/edit`,
      PUT `/users/{id}`, DELETE `/users/{id}`) — wrapped by the
      `EnsureUserIsAdmin` middleware (alias `admin`).
    - `/verify-email`
    - `/settings`
    - POST form actions: `/settings/profile`, `/settings/password`
- Minimal API compatibility:
    - `/api/v1/auth/*`
    - `/api/v1/me/*`
    - `/api/v1/password/*`
    - `/api/v1/email_verification/*`

## Authentication & roles

- Default guard: `users`.
- Guard driver: `database_token`.
- Login issues an HTTP-only bearer cookie through `Thinkycz\LaravelCore\Guards\DatabaseTokenGuard`.
- Inertia pages receive the current user through `HandleInertiaRequests` shared props
  (`auth.user.is_admin`, `auth.user.assigned_store_id`).
- Web form submissions use Laravel redirects, validation errors, and flash messages.
- Registration has been removed. The single main admin account (`test@test.com`)
  is seeded by `UserSeeder` and provisions additional limited accounts from the
  `/users` section. Limited users are pinned to exactly one store
  (`assigned_store_id`) and may only see Dashboard, Příjem zboží,
  Výdej / spotřeba, Výkazy (Statements), Inventura, Směny, Docházka, and
  Settings. Their Dashboard does not expose inventory statistics and instead
  provides a store-scoped live operations summary (current and next shifts,
  current attendance, breaks, and stale attendance warnings) plus direct action
  cards for the six store workflows. Store-scoped inputs are fixed and any
  cross-store access returns 403.

## Cookies

| name pattern                                      | description              |
| ------------------------------------------------- | ------------------------ |
| `{app_name}_{env}_database_token_users`           | local bearer token       |
| `__Host-{app_name}_{env}_database_token_users`    | non-local bearer token   |
| `{app_name}_{env}_session` / `__Host-..._session` | Laravel session/CSRF use |

## Tooling

| command              | description                         |
| -------------------- | ----------------------------------- |
| `composer run dev`   | Laravel server, queue, logs, Vite   |
| `npm run dev`        | Vite development server             |
| `npm run type-check` | Vue TypeScript check                |
| `npm run build`      | production frontend build           |
| `composer test`      | Laravel test suite                  |
| `make fix`           | Prettier and Pint formatting        |
| `make check`         | static analysis, lint, audit, tests |

## Env

Copy `.env.example` to `.env` and set:

- `APP_NAME`
- `APP_ENV`
- `APP_KEY`
- `APP_URL`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `REDIS_USERNAME`, `REDIS_PASSWORD` when Redis requires credentials
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- `TRUSTED_PROXIES`
- `SLACK_BOT_USER_OAUTH_TOKEN` when store-specific Slack notifications are enabled

## Slack notifications

- Each store may define its own optional Slack channel name or ID in the store administration form.
- A single deployment-wide bot token is read from `SLACK_BOT_USER_OAUTH_TOKEN`; no default channel is used.
- Attendance, finalized inventory, statement mutations, and manual stock movements produce queued Czech operational messages after the surrounding database transaction commits.
- Transfers are routed to both affected stores. Stores sharing the same configured channel receive one message for that activity.
- Missing tokens/channels and Slack enqueue failures do not change the result of the underlying application action.
- The Slack App must be installed in the workspace and invited to every configured private channel. Queue workers must be running to deliver messages.

## Deferred

- Inertia SSR is intentionally not enabled in v1.
- OpenAPI demo generation from the reference project is intentionally omitted.
- Catalog/order sample entities from the reference project are intentionally omitted.

## Inventory semantics

- Items are the catalog (`items` table): name, SKU, unit, purchase price.
- Deleting an item soft-deletes it from the active catalog when completed inventory rows reference it. Completed inventory history remains readable, while rows from open inventory drafts are removed. Items with stock-movement history remain protected from deletion.
  They do not carry stock on their own.
- Per-store stock lives on `store_items` (`store_id`, `item_id`, `quantity`).
  Quantity is the single source of truth for "what is on the shelf right
  now" and is updated transactionally by `InventorySessionService` and
  `StockMovementService`.
- The single stock ledger uses these terms consistently:
    - **příjem / incoming** — externally received goods added directly to one
      store.
    - **spotřeba / consumption** — goods that actually left inventory through
      operation; it affects consumption cost and forecast.
    - **přesun / transfer** — stock relocated between two stores; it has zero
      company-level consumption impact.
    - **inventurní vyrovnání / inventory reconciliation** — immutable ledger
      evidence for a physical-count difference.
- Limited users have focused `/stock-movements/create?mode=incoming` and
  `?mode=consumption` forms. Both are server-pinned to `assigned_store_id`;
  transfer, adjustment, cross-store, and backdated submissions remain
  forbidden.
- An open inventory draft has no finalized `counted_at`. The inventory form
  defaults to today's date on every open, permits a past date, and persists the
  selected date only when the entire draft is closed. Drafts never appear in
  inventory history, dashboard last-inventory data, or statistics.
- `/items` (Inventář) is a pure catalog list — it never exposes
  per-store quantity, value, or status. Those are properties of the
  `store_items` link, so they only render inside a store context.
- `/stores/{id}` is the only place where the current stock snapshot makes
  sense. The inventory table there exposes:
    - **Množství** (current `store_items.quantity`)
    - **Hodnota** (`quantity × items.purchase_price`)
    - **Stav** (`ok`, `due_soon`, `out`, or `no_data` from the stockout forecast)
    - **Vývoj (30 dní)** (30-day sparkline via
      `InventorySessionService::sparklineForItem`, sourced from
      `inventory_session_items`)
    - **Naposledy napočítáno** (timestamp of the most recent
      `inventory_sessions` row that contains the item for this store,
      formatted via `useCzechDate`).
    - **Prům. spotřeba / den** (average daily consumption from closed
      inventory intervals and explicit consumption; transfers are excluded).
    - **Dnů do vyprodání** (predicted days of stock left, derived from
      current quantity and average consumption).
- `/inventory-counts` is the focused data-entry surface. Each row is one
  catalog item with three quantity columns:
    - **Aktuální množství** — read-only, the current on-hand value from
      `store_items`.
    - **Poslední množství** — read-only, the quantity recorded in the
      previous inventory session for the same store/item (or `—` when
      there is none).
    - **Nové množství** — the input that becomes the new on-hand value
      when the form is saved.
      Saving creates a new `inventory_sessions` header, snapshot rows, and one
      linked inventory reconciliation for non-zero differences, then sets
      `store_items.quantity` to the counted value, all in one transaction.
- Historical snapshots are reconciled with
  `php artisan stockflow:backfill-inventory-consumption --dry-run`; after review,
  rerun without `--dry-run`. The command is idempotent and never changes current
  `store_items` quantities.
- `/inventory-counts/{session}` is the read-only detail of one
  inventory session. It lists every recorded item in alphabetical
  order with the new value and the previous value, so the operator can
  spot day-over-day deltas without a join.
- `/inventory-counts/history` is the audit log of inventory sessions
  (one row per save). Each row links to the matching show page; the
  page exposes store, item, and date-range filters (default 90 days).
