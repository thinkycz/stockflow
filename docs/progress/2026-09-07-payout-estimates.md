# Wolt ranges and historical Bolt VAT

Accepted scope: estimates only; no report imports, migrations or production writes.

- Implemented: exact Wolt range with transaction fees; sales-date Bolt VAT regimes; shared live summaries; range comparison, manual-only inferred Wolt periods and amber receipts; all locales.
- Verification: pre-commit `make fix` and `make check` passed: PHPStan (max), formatting, dependency audits, build/type-check, 133 JS tests, 1186 PHP tests (19 skipped, 56316 assertions), and all 86 browser tests. Payout browser checks cover Czech, Slovak and English on desktop/mobile. Final log: `/tmp/payout-commit-check.log`.
- Earlier verification: intermittent timeouts occurred in the recipe batch selector and once in the English date picker. The final full run passed without changing those expectations or enabling retries.
- Delivery: user requested commit and push to main; unrelated voucher export artifacts excluded. No production mutations.
- Review: Czech mobile review and Wolt receipt screenshots inspected. Explicit competing-period diagnostic preserved; closed snapshots and saved periods remain unchanged.
- Defaults: Wolt conservative scalar with range, no automatic amount-based Wolt period or verified receipt; Bolt charged VAT changes on sales day 2026-08-03 for all stores.
