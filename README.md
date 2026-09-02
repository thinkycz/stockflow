# StockFlow

StockFlow is an Inertia-first Laravel 13 inventory app for one company per deployment. A main administrator owns the catalog and stores; limited accounts work only with their assigned branch.

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

- `/login`, `/forgot-password`, `/reset-password`
- `/dashboard`
- `/verify-email`
- `/settings`
- POST form actions: `/settings/profile`, `/settings/password`

Minimal API-compatible auth endpoints remain under `/api/v1/auth`, `/api/v1/me`, `/api/v1/password`, and `/api/v1/email_verification`.

There is no public registration endpoint. After migrating an empty database,
provision the single main administrator with a password entered through hidden
console prompts, then verify the production identity boundary:

```sh
php artisan stockflow:admin:bootstrap owner@example.com
php artisan stockflow:identity:diagnose
```

Demo seeders, including the old `test@test.com / password` account, are restricted
to `local` and `testing`. Development, staging, and production Make targets never
run seeders. An existing installation still using the demo credential must rotate
it before the production target can pass:

```sh
php artisan stockflow:admin:bootstrap test@test.com --rotate
```

Cookie-authenticated API clients must first call `GET /api/v1/csrf-cookie` with
credentials enabled, then copy the readable `XSRF-TOKEN` cookie into the
`X-XSRF-TOKEN` header on POST, PUT, PATCH, and DELETE requests. Authorization
bearer clients do not require this CSRF handshake.

Before a single-company migration, back up MySQL and run:

```sh
php artisan stockflow:migrate-single-company --dry-run
php artisan stockflow:backfill-inventory-consumption --dry-run
```

For an existing deployment, release the code before applying the new migrations,
take a database backup, and run the single-company dry-run and write command
first. Migration `2026_07_19_000007` deliberately stops when incompatible root
accounts remain. After migrations, run the inventory backfill dry-run; it
supports `--chunk=200` and resumable `--after=<session-id>` checkpoints.

Application documentation is maintained in [docs/application_documentation.md](docs/application_documentation.md).
