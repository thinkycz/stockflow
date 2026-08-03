# UI pattern consistency pass

## Goal

Complete the fast and structural work from the 2026-08-03 UI/UX audit while
keeping the dashboard noticeboard-first and preserving all currently visible
operations, metrics, and recent movements.

## Progress

- Status: complete
- Active slice: none
- Inherited worktree: clean
- Blockers: none

## Slices

- [x] Add and verify shared page-header, tabs, loading, modal, menu, pagination,
      empty-state, and compact-icon patterns.
- [x] Remove unreachable dashboard analytics data and update its contract tests.
- [x] Migrate standard pages, forms, titles, tabs, searches, statuses, and
      validation to the shared patterns.
- [x] Complete navigation/toast accessibility and synchronize all locales.
- [x] Run targeted tests, frontend checks, and `make check`; record evidence.

## Decisions and boundaries

- Standard create/edit/settings width is `max-w-3xl`.
- `AppLayout` and `AuthLayout` own document titles for wrapped pages; standalone
  public and print pages continue to own their `<Head>` declarations.
- Hidden dashboard analytics are removed rather than restored. Visible metrics
  and recent movements remain.
- Existing `LoadingState` is reused instead of adding a competing spinner.
- Branding, the global background blur, noticeboard color semantics, auth visual
  direction, account-menu redesign, and route-level dashboard splitting remain
  deferred.

## Verification evidence

- `make fix`: passed (Prettier and Pint).
- `make check`: passed, including PHPStan at max, formatting checks, Composer
  and npm audits, Vue type-check, production build, 50 Vitest tests, and 717
  Pest tests with 19,919 assertions.
- Targeted Playwright coverage: 15 relevant scenarios passed across dashboard,
  noticeboard, shared UI consistency, gift vouchers, attendance, income and
  expenses, and payroll. Payroll was rerun against a fresh seed after the
  combined run exposed pre-existing shared test-state ordering.
- `git diff --check`: passed.
