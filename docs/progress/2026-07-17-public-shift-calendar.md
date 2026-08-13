# Public shift calendar progress

## Status

- Current phase: Multi-link management verification
- Overall status: implementation verified; dependency audit blocked
- Last updated: 2026-08-13

## Traceability

| ID  | Requirement                                   | Phase | Status   | Verification                                      |
| --- | --------------------------------------------- | ----- | -------- | ------------------------------------------------- |
| R1  | Store-level unguessable persistent links      | 4     | verified | Store controller tests + migration                |
| R2  | Admin-only link creation/deletion             | 4     | verified | ShiftShareLink store/destroy controller tests     |
| R3  | Unauthenticated read-only full store calendar | 2     | verified | SharedShiftIndexControllerTest                    |
| R4  | Month navigation and worker names             | 2     | verified | Controller tests + type-check/build               |
| R5  | Link-management modal on authenticated page   | 4     | verified | Type-check, production build, and Inertia tests   |
| R6  | CS/EN/SK translation parity                   | 3     | verified | I18nParityTest                                    |
| R7  | Independent revocation across public surfaces | 4     | verified | Destroy controller and shared-route feature tests |

## Blockers

- `composer audit` reports six advisories for the locked
  `league/commonmark <2.9.0` dependency required by Laravel.
- `npm audit` reports one high-severity advisory for locked
  `nanoid <3.3.17`.
- Browser clipboard interaction was not manually exercised; the existing
  clipboard fallback is retained and the production frontend build passes.

## Completed slices

- Phase 1: store token migration, Store APIs, admin JSON endpoint,
  authorization, and token-reuse coverage.
- Phase 2: public route/controller, store isolation, worker names,
  invalid-token handling, and a standalone read-only month calendar.
- Phase 3: copy-link UX with clipboard fallback, three-locale text, and fresh
  verification.
- Phase 4: migrated multi-link persistence, named creation, store-scoped
  revocation, admin management UI, three-locale text, and focused backend/type
  verification.

## Next

- Upgrade the affected dependency locks in a dedicated security-maintenance
  change, then rerun `make check` and optionally smoke-test clipboard behavior.
