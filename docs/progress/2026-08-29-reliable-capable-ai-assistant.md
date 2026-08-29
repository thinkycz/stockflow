# Reliable and capable AI assistant

## Objective

Make every native assistant reader expose the same authorized business facts as its human-facing page, and make durable turns preserve complete tool/result history across disconnects, retries, and long conversations.

## Delivery tracker

| Phase | Scope                                                            | Status   | Evidence                                                    |
| ----- | ---------------------------------------------------------------- | -------- | ----------------------------------------------------------- |
| 1     | Production reproduction and failing regression                   | Complete | Finance/statement regression passes                         |
| 2     | Shared read envelope, keyset cursor, safe errors and byte bounds | Complete | Cursor tamper/expiry/change and 64 KiB tests pass           |
| 3     | 20 concrete service-backed readers and typed datasets            | Complete | Catalog/schema/capability tests pass                        |
| 4     | Semantic context, tool-result integrity and audit correlation    | Complete | 2,302-row and parallel pairing tests pass                   |
| 5     | Atomic durable admission, resumable SSE and safe retry recovery  | Complete | Admission, replay, retry-lineage and post-action tests pass |
| 6     | Read route parity, diagnostics, ADR and operations docs          | Complete | Read parity: 119 assertions; diagnostic command covered     |
| 7     | `make fix`, focused suites, `make check`, `make e2e`             | Complete | 856 backend, 77 frontend unit, and 61 browser tests pass    |

## Invariants

- The catalog remains 20 read/write resource pairs plus `ask_user_choice`.
- Writes continue through native approvals and the same services as human UI actions.
- Main-admin-visible business/personnel data is returned only when relevant.
- Credentials, voucher codes, share tokens, binary content, and provider internals never enter tool results.
- Empty, unauthorized, incomplete, and changed datasets are distinct states.
- A tool call reaches the provider only with exactly one result or one unresolved approval.
