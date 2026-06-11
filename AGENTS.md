# AGENTS.md

## Purpose and scope

- This file captures project-specific architecture, workflows, and coding rules.
- `docs/guidelines.md` remains authoritative where it applies.

## Stack and runtime

- Laravel 13 on PHP 8.3.
- Inertia 3, Vue 3 Composition API, TypeScript, Tailwind 4, Vite.
- Local package dependency: `packages/thinkycz/laravel-core`.
- Authentication uses the core `database_token` guard and HTTP-only cookies.
- Runtime services: MySQL 8, Redis, cron, supervisor.

## Repo layout and key files

- `app/Http/Controllers/Web/` contains Inertia page and form controllers.
- `app/Http/Controllers/Api/` keeps minimal API-compatible auth endpoints.
- `app/Http/Middleware/HandleInertiaRequests.php` shares app/auth/flash props.
- `resources/js/` follows the Laravel Vue starter-kit shape:
    - `components/`, `composables/`, `layouts/`, `lib/`, `pages/`, `types/`.
- `routes/web.php` is the primary UI surface.
- `routes/api.php` is retained only for auth/me/password/email compatibility.
- `packages/thinkycz/laravel-core/` contains reusable framework helpers, guards, validation, and scaffolding stubs.

## Tooling and workflows

- Makefile is the primary workflow entry:
    - Provisioning: `make local|testing|development|staging|production`.
    - Formatting: `make fix`.
    - Validation: `make check` runs PHPStan, Prettier/Pint, audits, frontend build/type-check, and tests.
- Before each commit: run `make fix` then `make check`.
- Frontend checks are `npm run type-check` and `npm run build`.

## Backend conventions

- Keep app-level behavior thin and delegate framework behavior to `thinkycz/laravel-core`.
- Use `Thinkycz\LaravelCore\Support\Resolver` for framework helpers when following existing core patterns.
- Use validity classes such as `AuthValidity` for validation rules.
- Work with the logged-in user using `User::auth()` and `User::mustAuth()`.
- API controllers may use `Thinkycz\LaravelCore\Http\ApiFormRequest`; Inertia web controllers should use standard Laravel redirects and validation errors.
- DB writes should stay transactional when multi-step persistence is introduced.

## Frontend conventions

- Vue pages live in `resources/js/pages` and are resolved by `resources/js/app.ts`.
- Use `@/` for `resources/js` imports.
- Prefer small app UI components under `resources/js/components/ui`.
- Do not introduce a marketing landing page as the default screen; the first useful screen is the auth/dashboard workflow.
- Keep UI restrained, responsive, and task-focused.

## Scope notes

- The old reference catalog/order sample domain is intentionally omitted.
- OpenAPI demo routes and runtime generation are intentionally omitted from the default workflow.
- SSR is intentionally deferred; add it later through `@inertiajs/vite` if a project needs it.
