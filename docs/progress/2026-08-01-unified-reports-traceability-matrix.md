# Unified Reports Traceability Matrix

| Req ID | Source    | Requirement                                        | Phase | Status   | Verification                                 |
| ------ | --------- | -------------------------------------------------- | ----- | -------- | -------------------------------------------- |
| R1     | User plan | One `/reports` destination and legacy redirect     | 1–2   | Verified | Controller tests and Chromium flow           |
| R2     | User plan | Shared active-store and month filter               | 1–2   | Verified | Controller tests, type-check, build          |
| R3     | User plan | Summary plus accessible Finance and Inventory tabs | 2     | Verified | Type-check, build, Chromium flow             |
| R4     | User plan | Historical inventory at month end                  | 1     | Verified | Inventory report service tests               |
| R5     | User plan | Cutoff-aware forecasts and estimated valuation     | 1–2   | Verified | Inventory report service tests               |
| R6     | User plan | Navigation, locales, docs, and regression coverage | 2–3   | Verified | Unit tests, full `make check`, Chromium flow |
