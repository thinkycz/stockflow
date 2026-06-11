# Laravel Inertia Stack

Inertia-first Laravel 13 boilerplate with Vue 3, TypeScript, Tailwind, shadcn-vue-style primitives, and the Thinkycz Laravel core database-token guard.

## Development

```sh
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev
```

## Checks

```sh
npm run type-check
npm run build
composer test
make check
```

## Routes

- `/login`, `/register`, `/forgot-password`, `/reset-password`
- `/dashboard`
- `/verify-email`
- `/settings/profile`, `/settings/password`

Minimal API-compatible auth endpoints remain under `/api/v1/auth`, `/api/v1/me`, `/api/v1/password`, and `/api/v1/email_verification`.

Application documentation is maintained in [docs/application_documentation.md](docs/application_documentation.md).
