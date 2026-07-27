# Full application stabilization verification

## Outcome

The application now uses an immutable, classified stock ledger. Purchases,
transfers, consumption, adjustments, inventory reconciliations and reversals
have separate semantics. Transfers are excluded from consumption and margin.
Inventory is a resumable row-timestamped draft workflow, and all inventory
analytics share the same covered-interval forecasting service.

## Evidence

- PHPStan at level max passed.
- Prettier and Pint passed after `make fix`.
- Vue TypeScript check and Vite production build passed.
- Vitest passed: 3 files / 9 tests.
- Playwright passed: 18 Chromium browser scenarios, including unavailable
  public registration and deterministic locale switching.
- Pest passed: 482 tests / 9351 assertions.
- Composer and npm audits reported zero known vulnerabilities.
- Query-count regression covered Dashboard, Statistics, inventory entry and
  store detail with 30 SKUs.
- Decimal regression covered `1.250`, `0.250` and `0.001` through receipt,
  transfer, consumption and reversal.
- Backdating regression proves an administrator cannot post before an affected
  item's latest closed physical count.

## Deployment gate

Before migrating an existing MySQL database:

1. create and verify a database backup;
2. save `stockflow:migrate-single-company --dry-run` output;
3. run `stockflow:migrate-single-company`;
4. apply migrations;
5. save `stockflow:backfill-inventory-consumption --dry-run --chunk=200`;
6. run the backfill and retain its final checkpoint.

The local MySQL dry-run found no orphan accounts. Its schema was intentionally
not migrated or backfilled as part of implementation.
