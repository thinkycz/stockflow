# Bank Statement AI Import Progress

## Status

- Current phase: completed and verified
- Overall status: complete
- Last updated: 2026-09-02
- Blockers: none

## Requirement Matrix

| ID  | Requirement                                                  | Phase        | Status   | Verification                            |
| --- | ------------------------------------------------------------ | ------------ | -------- | --------------------------------------- |
| BS1 | Admin-only active-store upload and encrypted private archive | Backend      | complete | feature tests                           |
| BS2 | Queued structured OpenRouter PDF parsing                     | AI/queue     | complete | fake-agent job tests; opt-in live smoke |
| BS3 | Editable review, confirmation, reopen, and retry             | Web workflow | complete | controller and browser tests            |
| BS4 | Header/count/balance integrity checks                        | Domain       | complete | integrity and confirmation tests        |
| BS5 | Live card/marketplace reconciliation with CZK 5 tolerance    | Domain       | complete | reconciliation service tests            |
| BS6 | New archive/detail UI and Statements summary                 | Frontend     | complete | Vitest, type-check, build, Playwright   |
| BS7 | Route parity, translations, docs, and repository gates       | Integration  | complete | `make check`                            |

## Phases

### 1. Domain and persistence

- [x] Add failing service/model tests.
- [x] Add enums, migrations, factories, models, and typed getters.
- [x] Add integrity and reconciliation services.

### 2. AI and HTTP workflow

- [x] Add the structured parser agent and encrypted queue job.
- [x] Add upload, show, update, confirm, reopen, retry, and original-download controllers.
- [x] Add route parity classifications and feature tests.

### 3. Frontend

- [x] Add archive/upload and detail/review pages.
- [x] Add admin navigation and localized copy.
- [x] Add the monthly reconciliation panel to Statements.

### 4. Verification

- [x] Run focused backend and frontend tests.
- [x] Run formatting, PHPStan, type-check, build, audits, deployment smoke, and full tests through `make check`.
- [x] Record final evidence and remaining external smoke-test limitation.

## Constraints

- Do not modify `.env*` files.
- Do not commit the real bank statement as a test fixture.
- Do not mutate `Statement` or `StatementDay` records from this feature.

## Final Notes

- The real supplied statement was used only as design input and was not copied into the repository.
- Archived PDFs, raw AI responses, and sensitive transaction metadata are encrypted at rest.
- The live provider smoke test remains opt-in through the existing runtime flag; no `.env*` file was changed.
