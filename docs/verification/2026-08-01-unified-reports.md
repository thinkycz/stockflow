# Unified Reports Verification

Status: Verified on 2026-08-01.

## Evidence

- `make fix`: completed; Prettier and Pint formatted the repository.
- `make check`: passed PHPStan level max, Prettier, Pint, Composer/npm audits, platform checks, frontend type-check and build, 20 Vitest tests, and 594 Pest tests with 12,733 assertions.
- Report controller tests verify the combined payload, month, store scope, empty state, and legacy redirect.
- Inventory report service tests verify month-end rollback, transfer perspectives, later reversals, cutoff-aware forecasts, and current-price valuation.
- Performance coverage keeps the unified report under the inventory-screen query budget.
- `npm run e2e -- tests/e2e/dashboard.spec.ts`: 3 Chromium tests passed. The report path verifies redirect from `/reports/statistics`, one navigation item, Finance as the default tab, and switching to Inventory.

## Readiness

- Verdict: Ready for review and release.
- Blockers: None.
- Accepted limitation: historical inventory currency value is explicitly labeled as an estimate based on current purchase prices.
