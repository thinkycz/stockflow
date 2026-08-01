# Income and Expenses Traceability Matrix

| Req ID | Source        | Requirement                                       | Phase | Status   | Verification                                     |
| ------ | ------------- | ------------------------------------------------- | ----- | -------- | ------------------------------------------------ |
| R1     | approved plan | Admin-only active retail-store report             | 2     | verified | controller, middleware, and E2E tests            |
| R2     | approved plan | Five revenue channels and monthly commissions     | 1     | verified | service tests including monthly rounding         |
| R3     | approved plan | Incoming and transfer stock expenses per document | 1     | verified | service test with exclusions and store scope     |
| R4     | approved plan | Planned wages per worker                          | 1     | verified | service test with multiple historical rates      |
| R5     | approved plan | Persistent automatic-row overrides                | 1     | verified | service, controller, and E2E tests               |
| R6     | approved plan | Manual income/expense CRUD and copy               | 1-2   | verified | service, controller, and E2E tests               |
| R7     | approved plan | Close snapshot, mutation lock, and reopen refresh | 1-2   | verified | service, controller, and E2E tests               |
| R8     | approved plan | Separate tables and financial totals              | 2     | verified | TypeScript check, production build, and E2E test |
| R9     | approved plan | Sidebar placement and three locales               | 2     | verified | navigation, i18n parity, and E2E tests           |
| R10    | approved plan | Automated backend, frontend, and E2E verification | 3     | verified | repository check and focused Playwright test     |

Full command evidence is recorded in `docs/verification/2026-08-01-income-expenses.md`.
