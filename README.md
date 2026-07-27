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

There is no public registration endpoint. On an empty database `UserSeeder`
creates `test@test.com / password` and the initial warehouse. These known
credentials are an explicitly accepted deployment risk; change them through
account settings after provisioning.

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
