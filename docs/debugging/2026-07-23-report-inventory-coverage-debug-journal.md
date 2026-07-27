# Report Inventory Coverage Debug Journal

## Symptom

Opening the reports page failed in `StatementService::inventoryCoverage()` because
`Typer::assertString()` received `null` for `inventory_sessions.counted_at`.

## Reproduction

Create an open inventory draft with a saved item whose
`observation_started_at` is populated, then build a monthly report for that
store.

## Evidence

- Open drafts deliberately persist `inventory_sessions.counted_at` as `null`.
- Saved draft items can have a non-null `observation_started_at`.
- The coverage query selected those draft rows without filtering by session
  status.
- The regression test failed at `StatementService.php:635` before the fix.
- Other inventory interval queries restrict sessions to `status = closed`.

## Working Theory

Draft inventory rows were being treated as finalized coverage even though their
session has no final count timestamp.

## Checks Run

- Confirmed the schema migration made `inventory_sessions.counted_at` nullable.
- Confirmed the draft workflow saves item rows before the session is closed.
- Confirmed the focused regression test reproduced the reported assertion.

## Hypotheses Rejected

- `Typer::assertString()` returning an incorrect value: it correctly exposed a
  null value that violated the caller's assumption.
- Corrupt finalized inventory data: the null timestamp is valid for open drafts.

## Current Hypothesis

Confirmed: `inventoryCoverage()` omitted the finalized-session status filter.

## Next Probe

Verify that filtering coverage and last-inventory queries to closed sessions
passes the regression and surrounding statement service tests.
