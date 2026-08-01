# Public shift calendar verification

> Spec: [public shift calendar](../specs/2026-07-17-public-shift-calendar.md)  
> Plan: [implementation plan](../plans/2026-07-17-public-shift-calendar.md)  
> Progress: [phase tracker](../progress/2026-07-17-public-shift-calendar.md)

## Acceptance evidence

| Requirement                           | Evidence                                          | Status                           |
| ------------------------------------- | ------------------------------------------------- | -------------------------------- |
| Store-level persistent share token    | Migration plus token creation/reuse feature tests | verified                         |
| Store-scoped link generation          | Admin and assigned limited account tests          | verified                         |
| Unauthenticated read-only calendar    | Guest feature test and production frontend build  | verified                         |
| Store isolation and invalid-token 404 | Dedicated feature tests                           | verified                         |
| Copy-link interaction                 | TypeScript/build; Clipboard API with fallback     | verified with manual-browser gap |
| CS/EN/SK parity                       | `I18nParityTest`                                  | verified                         |

## Commands

- Focused PHP tests: `28 passed (88 assertions)`.
- Frontend unit tests: `4 passed`.
- Architecture tests: `58 passed (6765 assertions)`.
- `npm run type-check`: passed.
- `npm run build`: passed; public calendar chunk emitted.
- Scoped PHPStan at level max: `[OK] No errors`.
- Prettier and Pint: passed.
- Route registration: six shift routes registered, including
  `GET public/shifts/{token}` and `POST shifts/share`.

## Attendance rating extension (2026-08-01)

- The public calendar now receives badge-only attendance ratings and a complete
  `monthly_summary` without salary or detailed penalty reasons.
- Limited accounts can create and reuse the token for their assigned store;
  query overrides cannot switch the target store.

## Remaining evidence gap

- Clipboard permissions were not manually exercised in a signed-in browser.
  The implementation uses `navigator.clipboard.writeText()` and falls back to
  a temporary textarea plus `document.execCommand('copy')`.

## Pre-existing unrelated failures

- Full PHPStan reports errors in the unchanged local `laravel-core` package.
- Two statement tests hard-code 30 days and fail in July, which has 31 days.
