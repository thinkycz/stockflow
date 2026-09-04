# ADR 0010: Server-owned inventory row revisions

Status: accepted, September 4 remediation plan.

The persisted `client_version` column is retained for an additive-compatible
schema but now stores a server-owned revision. Its existing values initialize
revisions without rewriting historical rows. An absent row has revision zero.
The public contract uses `expected_revision` and returns `revision` plus the
authoritative row. A successful locked comparison increments the revision;
a mismatch returns HTTP 409 and the current row, never a false saved result.

The browser serializes requests per row, coalesces subsequent edits and tracks
the latest edit separately from request completion. A conflict preserves both
local and saved values. “Use saved value” accepts the returned row; “Reapply my
value” resubmits the local value using the latest returned revision and may
conflict again. Closing is blocked while any latest edit is pending, failed or
conflicted. Assistant callers use the same revision contract.

Cancellation and closing lock the session before checking its current state.
Repeated cancellation is idempotent; closed sessions cannot be cancelled.
Stock-ledger arithmetic and historical snapshots remain unchanged. Diagnostic
commands report existing anomalies without repairing them.
