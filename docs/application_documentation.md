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
    - `/login`, `/register`, `/forgot-password`, `/reset-password`
    - `/dashboard`
    - `/verify-email`
    - `/settings/profile`, `/settings/password`
- Minimal API compatibility:
    - `/api/v1/auth/*`
    - `/api/v1/me/*`
    - `/api/v1/password/*`
    - `/api/v1/email_verification/*`

## Authentication

- Default guard: `users`.
- Guard driver: `database_token`.
- Login/register issue an HTTP-only bearer cookie through `Thinkycz\LaravelCore\Guards\DatabaseTokenGuard`.
- Inertia pages receive the current user through `HandleInertiaRequests` shared props.
- Web form submissions use Laravel redirects, validation errors, and flash messages.

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
