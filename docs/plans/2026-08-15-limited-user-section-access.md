# Limited user section access

## Requirements

- [x] Administrators can enable or disable each limited-user operational section.
- [x] Existing and newly created limited users keep full access by default.
- [x] Disabled sections disappear from navigation and Dashboard-derived content.
- [x] Every authenticated read/write endpoint for a disabled section rejects access.
- [x] Administrators, Noticeboard, public shift links, and account basics remain unaffected.

## Progress

- Current phase: complete
- Status: ready for review
- Verification: PHPStan, Pint, Prettier, frontend type-check/build, 770 PHP tests, 68 Vitest tests, and the focused browser E2E scenario pass.
- Known external check: `npm audit --omit dev` reports the pre-existing high-severity `nanoid <3.3.18` advisory, so the aggregate `make check` command stops at that audit step.

## Decisions

- Store only disabled section keys in nullable `users.disabled_sections` JSON.
- Keep Dashboard always accessible and omit restricted module data server-side.
- Keep public unauthenticated shift links outside per-user restrictions.
