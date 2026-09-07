# Payout calendars and confirmed receipt indicators

Accepted scope: conservative Wolt/Bolt calendar suggestions, separate period/amount checks, administrator-only daily receipt indicators derived from all confirmed imports. No migrations, fee changes, report import or production writes.

## Work

- Implemented: calendar candidates and prevention of amount-only automatic matches against calendar evidence.
- Implemented: shared confirmed receipt projection, cross-month summaries and conflict checks.
- Implemented: localized accessible cell indicators and pending recalculation on edits, including summary invalidation.
- Verified: affected backend and controller tests, frontend type-check/build and 10 affected browser scenarios across all locales. Final `make fix` and `make check` passed.

## Evidence

Read-only provider inspection established six Wolt periods/month and Monday–Sunday Bolt periods for the inspected store. Actual settlement adjustments are absent from daily sales inputs; retain estimated fees and honest discrepancy states. No source PDFs are committed.

## Implementation decisions

- Calendar mismatches remain manual suggestions, including explicit periods outside tolerance. Zero-sales calendars cannot be selected automatically.
- Assigned overlapping periods retain amount diagnostics but are unresolved; inferred periods additionally enforce payout order. Confirmed views ignore draft reservations.
- Receipt details cover whole periods and use the shared accessible modal. Cash columns have no receipt marks.
- First full run passed PHPStan, lint, audits, type-check/build, 133 JS tests and 1150 PHP tests (19 skipped). Six browser failures came from newly added synthetic bank names and an incorrect save-button selector; all ten affected browser scenarios passed after correction.

## Final verification

`make fix` and `make check` both exited successfully. PHPStan, formatting, dependency audits, frontend type-check/build, deployment cache smoke checks, 133 frontend unit tests, 1151 PHP tests (19 skipped), and all 79 browser tests passed. Desktop and mobile receipt screenshots were visually inspected. Test-generated unrelated voucher PDF/PNG artifacts were restored to their original content. Production data was not modified.
