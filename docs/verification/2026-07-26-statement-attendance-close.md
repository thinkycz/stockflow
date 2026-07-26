# Statement Attendance Closure Verification

## Verdict

Ready for review. The statement modal, server-authoritative current-day attendance discovery, transactional bulk departure path, authorization boundaries, translations, and both statement save routes are verified.

## Evidence

- Focused statement controller suite: 27 tests passed with 113 assertions.
- Full Pest suite: 560 tests passed with 10,922 assertions.
- Targeted Chromium correction test passed with an admin seeing two active workers, their status indicators and live one-hour timers, then successfully using “Save and close all”; the preceding complete Chromium suite passed 23 tests.
- Frontend: TypeScript check, production Vite build, and 15 Vitest tests passed.
- PHPStan level max: no errors across 313 files.
- Prettier check, Pint check, and `git diff --check` passed.
- Platform requirements and strict Composer manifest validation passed.

## Covered behavior

- Admin and limited-user Inertia payloads list all current-day active employees at their authorized store in name order.
- Each payload row includes break-adjusted worked seconds and current-break state; the modal advances working timers live and pauses them during breaks.
- Both eligible save buttons refresh that prop immediately before deciding whether to open the modal.
- Admins use the selected owned store; stale sessions are excluded.
- Quick-entry and current-month grid saves support save-only and transactional save-and-close-all behavior.
- The server reloads and locks the eligible set, closes open breaks, records departure audits, and leaves stale sessions untouched.
- Admin and limited-user closure requests succeed for authorized stores; historical and foreign-store attempts remain rejected.
- English, Czech, and Slovak modal keys remain synchronized, with Czech consistently using “Brigádníci.”

## Verification notes

- A parallel build/Pest attempt caused expected Inertia 409 responses because the build rewrote the Vite manifest during requests. The serial full Pest rerun passed completely.
- `make check` initially stopped because installed PostCSS 8.5.16 matched a published advisory. The installed transitive package was updated to 8.5.23 and verified with `npm ls postcss`.
- A final external `npm audit` request was not approved because it would transmit dependency metadata, so that network-backed recheck remains unavailable.
- The final `make check` reached and passed PHPStan, Prettier, and Pint, then stopped because the sandbox could not resolve Packagist for `composer audit`; all remaining local targets were run separately and passed.
