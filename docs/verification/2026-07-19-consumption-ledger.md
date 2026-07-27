# Consumption ledger verification

## Result

Passed on 2026-07-19.

## Evidence

- `make fix`: passed.
- `make check`: passed.
- PHPStan: level max, 278 files, no errors and no baseline.
- Frontend: `vue-tsc --noEmit` and production Vite build passed.
- Unit frontend tests: 9 passed.
- Backend and architecture suite: 476 tests, 9038 assertions passed.
- Composer and npm security audits: no advisories found.

## Covered regressions

- Inventory decrease creates consumption; increase creates correction; no
  difference creates no reconciliation line.
- Manual consumption and unexplained inventory consumption are counted once.
- Transfers are excluded from consumption statistics and estimated margin.
- Limited users can consume only from their assigned store.
- Forecasts require closed inventory coverage and return `no_data` without it.
- Historical backfill supports `--dry-run`, preserves current stock, accounts
  for known movements, and is idempotent.

## Deployment order

1. Deploy code and run migrations.
2. Run `php artisan stockflow:backfill-inventory-consumption --dry-run` and
   review interval, consumption, correction, zero-difference, and skipped
   counts.
3. Run `php artisan stockflow:backfill-inventory-consumption` once approved.
4. Re-running the write command is safe and does not duplicate reconciliations.
