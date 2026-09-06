# Payout reconciliation and review layout

## Delivered behavior

- Pairing and amount verification are separate. Card, Wolt and Bolt use max(5 CZK, 1.25% of the absolute estimated payout), rounded HALF_UP to cents. Foodora remains at 5 CZK. Commission estimates are unchanged.
- Pairing requires exactly one daily record per date, scoped to the import owner and store. Missing and duplicate records have separate diagnostics.
- Missing Wolt/Bolt periods first use a single labelled settlement range with valid, fully specified ISO or Czech/Slovak dates before booking. Text is data; unlabelled references are not treated as date evidence.
- Otherwise, discovery searches 1–14 consecutive days within the 45 days before booking, across months. Complete daily records and positive estimated payouts are required.
- Only a sole qualifying candidate without competing overlap or payout-order conflicts can populate the draft automatically. Explicit dates can establish a period despite an amount discrepancy. Weekly boundaries only rank alternatives; they never resolve ambiguity.
- Existing periods and manually edited rows are never filled automatically. Assigned same-channel periods in other imports for the same owner/store also reserve sales. Up to three alternatives explain missing, ambiguous or conflicting periods. Conflicting candidates cannot be applied through the suggestion button; manual date fields remain available.
- Confirmed imports receive no automatic proposals. Reconciliation remains live. No database migrations, model/provider changes, inferred fee changes or automatic confirmations were introduced.
- The editable review uses existing date fields and draft save endpoint. Changed rows hide stale results until save. Tab-scoped recovery preserves unsaved rows only for the exact saved server version, clears after saving, and ignores malformed or obsolete snapshots. Browser storage being unavailable does not prevent editing.
- The review table is standalone, with labelled filters, separate paired/amount counters, visible confirmation blockers, and responsive dates and result cells. Monthly summaries and all three locales use the revised terminology.

## Verification

- Domain, architecture and affected controller checks: 99 passed.
- Targeted frontend tests: 10 passed, including malformed/stale draft recovery and changed-row detection.
- Full validation has passed PHPStan max, formatting, dependency audits, frontend type-check/build, 131 frontend tests, deployment cache smoke, and 1,139 PHP tests (19 skipped).
- Final `make fix` and `make check`: passed, including all 76 browser tests. The seven bank-statement browser tests cover draft recovery, candidate selection, saving, and all three locales. Desktop and mobile screenshots inspected; mobile date fields fit within the viewport.
- Test-generated unrelated gift-voucher print artifacts were restored to their original versions. Production data was not modified.

## Evidence and limits

Production Chrome inspection was read-only. Bolt's weekly payment cadence did not establish its sales periods; Wolt had competing near-amount matches. Synthetic regressions cover both patterns without importing the original PDF or copying private descriptions.

No algorithm can establish the real settlement period from amount proximity alone. Ambiguous payments remain for manual review; search bounds limit automatic suggestions, not manual date entry.
