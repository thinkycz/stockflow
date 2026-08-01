# Income and Expenses Verification

## Result

The monthly income and expenses section matches the approved specification and
is ready for review and deployment. No open implementation or verification
blockers remain.

## Evidence

- `make fix` completed successfully with Prettier and Pint.
- `make check` completed successfully on the final tree:
    - PHPStan analysed 343 files at maximum level with no errors.
    - Prettier and Pint checks passed.
    - Composer and npm audits reported no vulnerabilities.
    - PHP platform requirements and strict Composer validation passed.
    - Vue TypeScript checking and the production Vite build passed.
    - Vitest passed 20 tests in 6 files.
    - Pest passed 593 tests with 12,574 assertions.
- The focused Playwright scenario
  `tests/e2e/income-expenses.spec.ts` passed and covers admin navigation,
  manual-row creation, override editing, close/reopen, and limited-admin denial.
- Focused financial tests pass the five revenue channels, monthly commission
  rounding, Bolt cash inclusion, stock exclusions, multiple shift rates,
  override persistence/reset, closed snapshots, manual copy clamping and
  idempotency, warehouse denial, and closed-report mutation denial.
- `php artisan route:list --path=income-expenses` exposes all nine expected
  authenticated admin routes.
- `git diff --check` reports no whitespace errors.

## Release note

Apply `2026_08_01_000001_create_financial_reports.php` during deployment before
opening the new section to administrators.
