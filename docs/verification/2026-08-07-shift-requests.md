# Shift requests verification

## Automated checks

- `make fix` passed.
- `make check` passed, including PHPStan at maximum level, formatting checks, dependency audits, frontend type-check/build, Vitest, architecture tests, and the full Pest suite.
- Targeted controller and model tests passed for public token access, worker filtering, future-month rules, create-update-delete toggle semantics, locking, store isolation, and admin-only props.

## Chrome runtime verification

Verified on 2026-08-07 against an isolated, freshly migrated testing database seeded by `Database\\Seeders\\E2ESeeder`:

1. The public shift calendar exposed the request-entry action and opened the token-protected request page.
2. The request page defaulted to September 2026, with navigation to August disabled.
3. Selecting `E2E Worker`, 09:00-17:00, and September 15 created a visibly distinct dashed request card.
4. The admin September calendar hid requests by default; enabling `Show requests` displayed the same distinct card.
5. Locking September changed the admin action to `Unlock requests` and showed a success notification.
6. Reloading the public request page showed the locked state, disabled selection and every calendar day, while preserving the existing request for viewing.
7. At a 390 px viewport, both public calendar pages render only the full-month calendar without a view toggle, day detail, or redundant full-month header; request cards still include the worker, time, and `Request` label.
8. Chrome DevTools reported no JavaScript console messages during the exercised flow.

## Readiness

Ready for review and deployment after running the new database migration. No implementation or verification blockers remain.
