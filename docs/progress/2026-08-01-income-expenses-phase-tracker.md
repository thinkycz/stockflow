# Income and Expenses Phase Tracker

## Status

- Current phase: Phase 4
- Overall status: verified
- Last updated: 2026-08-01

## Phase 1: Persistence and calculation

- Status: complete
- [x] Add schema and models.
- [x] Add validation and calculation/lifecycle service.
- [x] Add service tests.
- Blockers: none.

## Phase 2: Web surface

- Status: complete
- [x] Add routes and controllers.
- [x] Add Inertia page and interactions.
- [x] Add sidebar and translations.
- [x] Add controller/navigation tests.
- Blockers: none.

## Phase 3: Verification

- Status: complete
- [x] Add focused E2E coverage.
- [x] Run repository checks.
- [x] Close traceability and verification evidence.
- Blockers: none.

## Phase 4: Recurring monthly expenses

- Status: complete
- [x] Add schema, models, factories, and effective-dated service behavior.
- [x] Add admin routes, management modal, row rendering, and translations.
- [x] Add domain, controller, Inertia, frontend, and E2E coverage.
- [x] Run full verification and refresh closeout evidence.
- Blockers: none.

## Decisions

- Open reports are computed live; only overrides and manual rows are persistent.
- Closed reports render their saved snapshot.
- Reopening preserves edits but resumes live calculations.
- Warehouse selection renders an empty state and cannot be mutated.
- Recurring expenses remain live automatic sources and use the existing monthly
  override mechanism; closed report snapshots stay immutable.
- Definition changes are effective-dated, and termination is non-destructive.

## Next

Apply the recurring-expense migration during deployment. No implementation or
verification blockers remain for this phase.
