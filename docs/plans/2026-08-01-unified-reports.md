# Unified Reports Implementation Plan

1. Protect the combined monthly contract and historical cutoff behavior with feature and service tests.
2. Extract inventory reporting into a service and compose it with the financial report in `ReportController`.
3. Consolidate the Vue pages into one summary-and-tabs experience with one month filter.
4. Remove the duplicate navigation destination, retain the legacy redirect, and synchronize docs and locales.
5. Run targeted and repository-wide verification and record the evidence.
