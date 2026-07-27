# Public shift calendar progress

## Status

- Current phase: Complete
- Overall status: verified
- Last updated: 2026-07-17

## Traceability

| ID  | Requirement                                   | Phase | Status   | Verification                                                          |
| --- | --------------------------------------------- | ----- | -------- | --------------------------------------------------------------------- |
| R1  | Store-level unguessable persistent link       | 1     | verified | Feature tests + migration                                             |
| R2  | Admin-only link creation/retrieval            | 1     | verified | ShiftShareControllerTest                                              |
| R3  | Unauthenticated read-only full store calendar | 2     | verified | SharedShiftIndexControllerTest                                        |
| R4  | Month navigation and worker names             | 2     | verified | Controller tests + type-check/build                                   |
| R5  | Copy-link button on authenticated shift page  | 3     | verified | Type-check/build; browser clipboard permission not manually exercised |
| R6  | CS/EN/SK translation parity                   | 3     | verified | I18nParityTest                                                        |

## Blockers

- None.

## Known pre-existing verification issues

- Full PHPStan reports errors in the unchanged local `laravel-core` package.
- Two statement tests hard-code 30 days and fail in July, which has 31 days.

## Completed slices

- Phase 1: store token migration, Store APIs, admin JSON endpoint,
  authorization, and token-reuse coverage.
- Phase 2: public route/controller, store isolation, worker names,
  invalid-token handling, and a standalone read-only month calendar.
- Phase 3: copy-link UX with clipboard fallback, three-locale text, and fresh
  verification.

## Next

- Optional future scope: token rotation/revocation controls.
