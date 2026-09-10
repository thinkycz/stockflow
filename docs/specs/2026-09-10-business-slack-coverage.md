# Business Slack notification coverage

## Contract

Implements the accepted September 10 plan and supersedes the finance/payroll edit and noticeboard exclusions in the August 2 specification. All hooks live in shared domain services, covering web and assistant callers alike. Existing store/company routing and Czech messages with Prague timestamps remain in effect. New store links include `store_id` and the relevant period.

| Domain             | Notify                                                                                                            |
| ------------------ | ----------------------------------------------------------------------------------------------------------------- |
| Finance            | Manual income/expense create, update, delete; calculated amount override/reset; previous-month copy (one summary) |
| Recurring expenses | Create, effective-version change, termination                                                                     |
| Payroll            | Report worker add/remove; wage override/reset; adjustment create/update/delete; tip distribution (one summary)    |
| Shifts             | Create including quick-add, update, delete, request approval, request month lock/unlock                           |
| Attendance         | Restore voided attendance; manual shift matching; batch matching (one summary)                                    |
| Bank statements    | Confirm, reopen, delete                                                                                           |
| Noticeboard        | Create, meaningful edit, trash, restore                                                                           |

Existing attendance, checklist milestones, report close/reopen, recipe test results, vouchers, inventories, sales and stock movement coverage is retained.

## Noise and payload policy

- Unchanged saves, empty copy/match batches, existing quick-add shifts, repeated attendance void/restore and unchanged request locks do not notify. Existing sales save/clear/restore and attendance correction messages also suppress unchanged business state.
- Shift request approval sends only an approval event, including when it consumes a request matching an existing shift. Bulk tip distribution and attendance matching do not emit per-record events.
- Configuration/catalog CRUD, personal settings, drafts, autosaves, public availability toggles, reads and background cleanup remain silent. Noticeboard color/size-only changes and permanent removal from the recycle bin remain silent.
- Financial messages show amounts, previous amounts for edits/overrides, direction and date/period. Recurring expense messages identify the effective month.
- Payroll messages identify the worker or affected count without individual wage rates, adjustment amounts or reasons. Tip distribution shows its aggregate total.
- Noticeboard messages contain only a bounded title. Bank messages contain the internal import ID and period, without account numbers, document contents or transaction data. No private notes, reasons or attachments are copied to Slack.
- User-supplied field text is bounded before Slack escaping; notifications cap their fact count. Each new activity type has localized headings and digest labels/categories. Safe new facts are included in digests without altering financial calculations.

## Delivery

Activities are journaled inside the business transaction and Slack events dispatch after commit. A rollback leaves neither a journal entry nor a Slack notification. Missing tokens/channels skip delivery; Slack enqueue/delivery failure retains existing failure isolation. No migrations, new settings or public endpoints are required. Restart queue workers on deployment so they understand new activity enum values. Existing archived digests remain immutable.

## Verification tracking

- New business coverage tests: finance, recurring expenses, payroll, batching, shifts, attendance, noticeboard, bank lifecycle, routing, real payload rendering, rollback, authorization and digest inclusion.
- Web and assistant financial entry points assert the same activity contract.
- Every operational enum case is rendered with its real translation and digest mapping; long fields are checked for escaping and bounds.
- `make fix` and `make check` passed: PHPStan at max, formatting, dependency audits, frontend type-check/build, 136 frontend unit tests, production cache smoke checks, 1,262 PHP tests (19 skipped), and 86 browser tests.
- Slack delivery was mocked; no live messages were sent. Pre-existing voucher PDF/screenshot changes were preserved after the browser suite regenerated those artifacts.
