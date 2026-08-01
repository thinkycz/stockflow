# Income and Expenses Verification

## Result

The monthly income and expenses section, including recurring monthly expenses,
is fully verified and ready for handoff. No known implementation or verification
blockers remain.

## Evidence

- The changed files were formatted successfully with Prettier and Pint.
- `make check` completed successfully on the final tree:
    - PHPStan analysed 368 files at maximum level with no errors.
    - Prettier and Pint checks passed.
    - Composer and npm audits reported no vulnerabilities.
    - PHP platform requirements and strict Composer validation passed.
    - Vue TypeScript checking and the production Vite build passed.
    - Vitest passed 43 tests in 13 files.
    - Pest passed 615 tests with 14,195 assertions.
- The focused Playwright scenario
  `tests/e2e/income-expenses.spec.ts` passed after the final build and covers
  navigation to the dedicated recurring-expense page, recurring-expense creation,
  a future effective change, exclusive termination, historical stability, absence
  of the previous-month copy button, manual-row creation, override editing,
  incoming/transfer direction labels, close/reopen, and limited-admin denial.
- Focused financial tests pass the five revenue channels, monthly commission
  rounding, Bolt cash inclusion, stock exclusions, multiple shift rates,
  stock movement source/destination metadata and snapshot persistence,
  override persistence/reset, closed snapshots, manual copy clamping and
  idempotency, recurring due-day clamping, version history, exclusive
  termination, warehouse denial, foreign-store denial, and closed-report
  mutation denial.
- `php artisan route:list --path=income-expenses --json` exposes all 13 expected
  authenticated admin routes, including the recurring-expense page and its
  create/change/terminate mutations.
- `git diff --check` reports no whitespace errors.

## Release note

Apply `2026_08_01_000001_create_financial_reports.php` during deployment before
opening the section to administrators. Also apply
`2026_08_01_000004_create_financial_recurring_expenses.php` before enabling
recurring-expense management. The recurring-expense migration uses a MySQL-safe
explicit foreign-key name and can repair the partial state left by the failed
release `74570063`.
