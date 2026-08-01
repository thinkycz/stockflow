# Attendance row actions verification

## Claim

The attendance page is driven by one standalone responsive table. It groups
today's shifts by worker, keeps active unscheduled workers visible, exposes all
valid attendance transitions in the row, and presents monthly attendance
quality accessibly. A separate panel above the table shows a selected worker's
live work or break timer and provides the off-schedule arrival action.

## Evidence

- `php artisan test tests/Feature/App/Http/Controllers/Web/Attendance tests/Feature/App/Services/AttendanceRatingServiceTest.php`
  passed 12 tests with 78 assertions.
- Focused PHPStan analysis passed at the repository's maximum level.
- `npm run type-check` and `npm run build` passed.
- `npm run test:unit -- tests/Unit/attendance-table-contract.test.ts`
  passed three table-contract tests.
- `npx playwright test tests/e2e/attendance.spec.ts` passed four Chromium
  scenarios covering the restored live timer, transitions, off-schedule and
  outside-window confirmations, and the 390 px layout without horizontal
  overflow.
- A screenshot from the mobile browser run was inspected for the standalone
  card presentation, visible labels, quality indicator, status, and actions.

## Repository-wide gate

`make check` passed after the final implementation and formatting changes. It
included PHPStan, Prettier, Pint, Composer and npm audits, type-check, production
build, all 38 Vitest tests, and 613 PHP tests with 14,111 assertions.

## Verdict

The attendance row-action slice is fully verified and ready for handoff. No
known blocker remains in this scope.
