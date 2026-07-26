# Statement Attendance Closure Traceability Matrix

| Req ID | Source        | Requirement                                                   | Phase | Tasks                               | Status   | Verification                                    | Notes                                    |
| ------ | ------------- | ------------------------------------------------------------- | ----- | ----------------------------------- | -------- | ----------------------------------------------- | ---------------------------------------- |
| R1     | approved plan | Expose all active current-day employees to limited users      | 1     | index query, Inertia prop, tests    | verified | controller tests                                | Empty for admins and ineligible sessions |
| R2     | approved plan | Modal lists employees before both eligible statement saves    | 2     | Vue state, modal, translations      | verified | Chromium smoke, type-check, build               | Dismissal cancels the pending save       |
| R3     | approved plan | Save-only leaves attendance unchanged                         | 1–2   | form flag, controller behavior      | verified | today-save feature test                         | Applies to both save endpoints           |
| R4     | approved plan | Save-and-close closes the latest eligible set transactionally | 1     | locking, departures, rollback tests | verified | controller tests and Chromium smoke             | Browser list is informational            |
| R5     | approved plan | Admin, historical, foreign, and stale cases remain protected  | 1–3   | authorization and regression tests  | verified | controller tests                                | Stale sessions require admin correction  |
| R6     | approved plan | Repository quality gates pass                                 | 3     | focused checks, fix, full check     | verified | PHPStan, formatting, build, 596 automated tests | External npm re-audit was not approved   |
