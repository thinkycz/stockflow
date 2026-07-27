# Statement Attendance Closure Plan

## Phase 1: Backend contract

- Add failing controller tests for active-attendance props and atomic bulk closure.
- Add current-day active-session discovery and transactional statement-save orchestration.
- Extend both statement save requests with the optional closure flag.

## Phase 2: Statement modal

- Intercept both eligible save paths and refresh the attendance-only Inertia prop before deciding whether to prompt.
- List active employee names and provide save-only and save-and-close actions.
- Add synchronized English, Czech, and Slovak translations.

## Phase 3: Verification

- Run focused PHP tests and frontend checks.
- Run `make fix`, `make check`, and record fresh evidence.
