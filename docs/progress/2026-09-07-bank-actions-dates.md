# Czech date pickers and bank statement actions

Accepted plan: Czech visible dates with ISO contracts, shared calendar/time picker, delete and AI reanalysis, on-demand Wolt/Bolt recommendations for edited and populated rows, removal of upload disclosure copy. No production mutations.

- Implemented: lifecycle actions, deletion race fencing and retryable private file cleanup.
- Implemented: read-only recommendations from validated draft snapshots.
- Implemented: shared accessible calendar/time picker, Czech date rendering, localized lifecycle actions and removal of upload notice.
- Verified: targeted service/controller tests and browser actions in all locales; date bounds, leap date and time selection on inventory/stock movement forms. Delayed recommendation replies are discarded and saved date payloads remain ISO.
- Completed: `make fix` and `make check` passed, including PHPStan, formatting, audits, type-check, build, deployment cache smoke checks, 133 frontend unit tests, 1,163 backend/architecture tests (19 skipped), and 83 browser tests. Mobile calendar screenshots reviewed in Czech, Slovak and English. Generated unrelated voucher artifacts restored; no production records changed.

Deletion stages an encrypted private cleanup marker under the database row lock and removes the source after commit. Scheduled maintenance retries storage failures. No new database schema is needed. Failed reanalysis restores review only when previous data exists; active generations and deleted IDs fence late parser callbacks. Recommendations use validated in-memory rows, exclude the target’s old reservation, and discard replies for changed drafts.
