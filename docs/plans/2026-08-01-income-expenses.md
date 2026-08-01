# Monthly Store Income and Expenses Plan

## Phase 1: Persistence and calculation

- Add report, override, and manual-row tables, models, factories, and validation.
- Build a scoped service for source calculation, overrides, copy, close, reopen,
  and snapshot rendering.
- Cover revenue, stock, wage, lifecycle, and isolation behavior with service tests.

## Phase 2: Web surface

- Add admin routes and thin Inertia controllers for every mutation.
- Add the monthly page, manual-row editor, override controls, lifecycle actions,
  active-store behavior, sidebar entry, and three-locale translations.
- Add controller and navigation tests, including limited-user and warehouse rules.

## Phase 3: Verification

- Add a focused E2E flow for navigation, manual entries, override, and closing.
- Run formatters, static analysis, unit/feature tests, frontend type-check/build,
  and the focused E2E test.
- Record requirement-level evidence and any remaining gaps.

## Phase 4: Recurring monthly expenses

- Add store-scoped recurring-expense definitions and effective-dated versions.
- Resolve one automatic monthly row per applicable definition, including due-day
  clamping, lifecycle boundaries, standard overrides, and snapshot behavior.
- Add admin management routes and a modal for create, effective-month change,
  and non-destructive termination in all three locales.
- Cover domain, authorization, Inertia, frontend, and browser scenarios, then
  rerun the full repository verification gate.
