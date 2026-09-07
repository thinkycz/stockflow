# Marketplace commission VAT correction

Accepted: Wolt/Foodora 30%, Bolt 35% excluding 21% VAT. Shared exact decimal calculation, commission then VAT rounded HALF_UP to cents. Bank transfers exclude already received Bolt Cash; financial net revenue includes it. No tax-credit calculation, migrations, production writes or changes to card fees, tolerances, calendars or saved periods.

- Implemented: shared calculation and localized breakdown across reconciliation, candidates, receipt indicators, statement reports and financial income rows. Existing numeric fields remain compatible; new breakdown fields are decimal strings.
- Verified: `make fix` and `make check` completed successfully. PHPStan, formatting, dependency audits, type-check, build, deployment cache smoke checks, 133 frontend unit tests, 1,177 backend/architecture tests (19 skipped), and 86 browser tests passed.
- Verified: common results across all services, Bolt Cash separation, rounding after period aggregation, tolerance boundaries, nonpositive estimates, no writes to confirmed periods, preserved legacy snapshots and manual overrides. Deliberate mismatch fixtures remain mismatches.
- Reviewed: desktop and mobile breakdowns in Czech, Slovak and English; keyboard expansion and amount formatting. Unrelated generated voucher artifacts restored. No production records changed.

Closed financial snapshots and manual overrides remain authoritative; live historical estimates use the corrected calculation.
