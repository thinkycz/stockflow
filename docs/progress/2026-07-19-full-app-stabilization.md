# Full application stabilization progress

## Baseline

- Worktree was clean when stabilization started.
- The prior consumption-ledger implementation was fully verified: 476 backend
  tests and 9038 assertions, plus PHPStan max, formatting, audits, frontend
  type-check/build, and Vitest.

## Stage status

- [x] Stage 1 — accounts, registration, numbering, limited consumption
- [x] Stage 2 — immutable ledger, inventory drafts, analytics, backfill
- [x] Stage 3 — decimal quantities, performance, statements, UX, docs
- [x] Final verification and release-readiness

## Current slice

Implementation and local verification complete. Production data migrations are
intentionally pending an operator-created backup and saved preflight output.

## Blockers

Production rollout requires the documented backup/preflight order. The local
MySQL schema is intentionally still on migrations through
`2026_07_19_000002`; no production-like data was mutated during development.

## Verification

- `make fix`: passed.
- PHPStan level max: passed using `--debug` because the workspace sandbox
  prohibits PHPStan's local parallel TCP listener.
- Prettier and Pint checks: passed.
- TypeScript type-check and Vite production build: passed.
- Vitest: 3 files, 9 tests passed.
- Playwright: 18 Chromium browser scenarios passed.
- Pest: 482 tests, 9351 assertions passed.
- Query-count regression: all four inventory screens remained under the
  40-query ceiling with 30 SKUs.
- Composer and npm security audits: zero vulnerabilities.
- Single-company local MySQL dry-run: passed and reported no orphan accounts.
- Inventory backfill dry-run correctly refused the pre-migration local schema;
  it must run after migrations `000003`–`000007`.
