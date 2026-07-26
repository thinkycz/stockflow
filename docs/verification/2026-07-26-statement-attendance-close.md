# Statement Attendance Closure Verification

## Verdict

Ready for review. The statement modal, server-authoritative current-day attendance discovery, transactional bulk departure path, authorization boundaries, translations, and both statement save routes are verified.

## Evidence

- Focused statement controller suite: 26 tests passed with 99 assertions.
- Full Pest suite: 559 tests passed with 10,909 assertions.
- Complete Chromium suite: 23 tests passed, including the limited user seeing two active employee names and successfully using “Save and close all.”
- Frontend: TypeScript check, production Vite build, and 14 Vitest tests passed.
- PHPStan level max: no errors across 313 files.
- Prettier check, Pint check, and `git diff --check` passed.
- Composer audit, platform requirements, and strict manifest validation passed during `make check`.

## Covered behavior

- Limited-user Inertia payload lists all current-day active employees at the assigned store in name order.
- Both eligible save buttons refresh that prop immediately before deciding whether to open the modal.
- Admins receive an empty list; stale sessions are excluded.
- Quick-entry and current-month grid saves support save-only and transactional save-and-close-all behavior.
- The server reloads and locks the eligible set, closes open breaks, records departure audits, and leaves stale sessions untouched.
- Admin and historical closure attempts are rejected without persisting the statement.
- English, Czech, and Slovak modal keys remain synchronized.

## Verification notes

- A parallel build/Pest attempt caused expected Inertia 409 responses because the build rewrote the Vite manifest during requests. The serial full Pest rerun passed completely.
- `make check` initially stopped because installed PostCSS 8.5.16 matched a published advisory. The installed transitive package was updated to 8.5.23 and verified with `npm ls postcss`.
- A final external `npm audit` request was not approved because it would transmit dependency metadata, so that network-backed recheck remains unavailable.
