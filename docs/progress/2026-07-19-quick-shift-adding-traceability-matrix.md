# Quick Shift-Adding Traceability Matrix

| Req ID | Source       | Requirement                                          | Phase | Tasks                               | Status      | Verification                                    | Notes                             |
| ------ | ------------ | ---------------------------------------------------- | ----- | ----------------------------------- | ----------- | ----------------------------------------------- | --------------------------------- |
| R1     | written spec | Store-specific preset CRUD with name and valid times | 1     | preset persistence and controllers  | verified    | focused model/controller tests pass             | no seeded defaults                |
| R2     | written spec | Admin-only preset props and management               | 1, 3  | index props and modal               | verified    | controller tests, type-check, build             | limited users remain read-only    |
| R3     | written spec | Explicit employee/preset quick-add mode              | 3     | toolbar and calendar interaction    | implemented | type-check and production build                 | runtime browser smoke unavailable |
| R4     | written spec | Immediate idempotent day assignment                  | 2, 3  | quick-add endpoint and client state | verified    | endpoint tests, type-check, build               | exact duplicate returns exists    |
| R5     | written spec | Confirm overlaps across all write flows              | 2, 3  | conflict service and confirmations  | verified    | service/controller tests, build                 | adjacent times allowed            |
| R6     | written spec | Preserve time and pay snapshots                      | 2     | assignment persistence              | verified    | quick-add and existing rate snapshot tests pass | no preset foreign key on shifts   |
| R7     | written spec | Localized feedback in cs/en/sk                       | 3     | translations                        | verified    | frontend/backend locale parity tests            | locale parity preserved           |
| R8     | written spec | Public calendar and limited-user behavior unchanged  | 4     | regression verification             | verified    | shared calendar and limited-user tests pass     | no public API changes             |
