# Consumption ledger progress

## Baseline

- Worktree was clean on `main` before implementation.
- Relevant baseline: 51 tests passed with 212 assertions.
- Existing behavior incorrectly treats `outgoing` transfers as consumption and
  overwrites stock during inventory without a ledger event.

## Phase status

- [x] Schema and domain model
- [x] Movement and inventory write flows
- [x] Historical backfill
- [x] Statistics and UI
- [x] Documentation and full verification

## Current slice

Completed. Ready for migration and reviewed historical backfill.

## Blockers

None.

## Verification

- `make fix` passed.
- `make check` passed, including PHPStan max, Prettier, Pint, Composer/npm
  audits, frontend type-check/build, Vitest, architecture tests, and the full
  Pest suite.
- Full backend result: 476 tests, 9038 assertions.
- Historical backfill has automated dry-run, write, current-stock preservation,
  known-movement, and idempotency coverage.
