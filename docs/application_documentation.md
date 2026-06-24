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

| package                         | description                         |
| ------------------------------- | ----------------------------------- |
| `thinkycz/laravel-core`         | internal Laravel core package       |
| `inertiajs/inertia-laravel`     | Laravel server adapter for Inertia  |
| `@inertiajs/vue3`               | Vue client adapter for Inertia      |
| `@inertiajs/vite`               | Inertia Vite integration            |
| `vue`                           | frontend framework                  |
| `tailwindcss`                   | styling system                      |
| `class-variance-authority/clsx` | shadcn-vue-compatible class helpers |

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
  (`assigned_store_id`) and may only see Dashboard, Výkazy (Statements),
  Inventura, and Settings — the store select is fixed and any cross-store
  access returns 403.

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

## Deferred

- Inertia SSR is intentionally not enabled in v1.
- OpenAPI demo generation from the reference project is intentionally omitted.
- Catalog/order sample entities from the reference project are intentionally omitted.

## Inventory semantics

- Items are the catalog (`items` table): name, SKU, unit, purchase price.
  They do not carry stock on their own.
- Per-store stock lives on `store_items` (`store_id`, `item_id`, `quantity`).
  Quantity is the single source of truth for "what is on the shelf right
  now" and is updated transactionally by `InventorySessionService` and
  `StockMovementService`.
- `/items` (Inventář) is a pure catalog list — it never exposes
  per-store quantity, value, or status. Those are properties of the
  `store_items` link, so they only render inside a store context.
- `/stores/{id}` is the only place where the current stock snapshot makes
  sense. The inventory table there exposes:
    - **Množství** (current `store_items.quantity`)
    - **Hodnota** (`quantity × items.purchase_price`)
    - **Stav** (`ItemStockStatusEnum::fromQuantity($quantity)` — in_stock /
      low_stock / out_of_stock)
    - **Vývoj (30 dní)** (30-day sparkline via
      `InventorySessionService::sparklineForItem`, sourced from
      `inventory_session_items`)
    - **Naposledy napočítáno** (timestamp of the most recent
      `inventory_sessions` row that contains the item for this store,
      formatted via `useCzechDate`).
    - **Prům. spotřeba / den** (average daily consumption computed from
      outgoing movements in the configured window).
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
  Saving creates a new `inventory_sessions` header plus its
  `inventory_session_items` rows and upserts the matching
  `store_items.quantity`, all in one transaction. Statistical columns
  are not rendered on this page — they live on the store detail page.
- `/inventory-counts/{session}` is the read-only detail of one
  inventory session. It lists every recorded item in alphabetical
  order with the new value and the previous value, so the operator can
  spot day-over-day deltas without a join.
- `/inventory-counts/history` is the audit log of inventory sessions
  (one row per save). Each row links to the matching show page; the
  page exposes store, item, and date-range filters (default 90 days).
