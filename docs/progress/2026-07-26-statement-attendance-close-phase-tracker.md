# Statement Attendance Closure Phase Tracker

## Status

- Current phase: Phase 3 — verification
- Overall status: verified
- Last updated: 2026-07-26

## Phase 1: Backend contract

- Goal: expose eligible attendances and close the server-authoritative set atomically with statement saves.
- Status: verified
- Tasks:
    - [x] Add failing feature tests.
    - [x] Add active-attendance discovery.
    - [x] Add validated transactional bulk closure to both save paths.
- Blockers: none.

## Phase 2: Statement modal

- Goal: add the pre-save choice to both current-day save paths.
- Status: verified
- Tasks:
    - [x] Add modal state and form flags.
    - [x] Render the employee list and actions.
    - [x] Add synchronized translations.
- Blockers: none.

## Phase 3: Verification

- Goal: prove specification compliance and repository health.
- Status: verified
- Tasks:
    - [x] Run focused backend and frontend checks.
    - [x] Run repository formatting and validation.
    - [x] Complete verification evidence and traceability.
- Blockers: external npm re-audit approval was denied; the formerly vulnerable installed PostCSS is locally verified at 8.5.23.

## Decisions

- Only current-day sessions in `Europe/Prague` are eligible.
- The server reloads the full eligible set when closure is requested.
- Admins and limited users both participate for their authorized store; historical and stale-session behavior remains unchanged.

## Deferred

- None.

## Next

- Ready for review.
