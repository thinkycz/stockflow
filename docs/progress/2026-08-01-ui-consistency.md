# UI consistency progress

## Current state

- Status: complete
- Active phase: release-ready
- Inherited worktree: filter/header consistency changes in eight Vue pages and
  `tests/Unit/page-filter-layout-contract.test.ts`
- Browser verification uses the deterministic Playwright testing server because
  the configured Herd URL is unavailable in this environment.

## Traceability

| Requirement                       | Status   | Evidence                           |
| --------------------------------- | -------- | ---------------------------------- |
| Shared role components            | Complete | Type-check and production build    |
| Page and component migration      | Complete | UI contract tests and route crawl  |
| Accessible central dialogs        | Complete | Playwright keyboard/focus scenario |
| UI contract tests                 | Complete | 35 Vitest tests pass               |
| Desktop/mobile route verification | Complete | 10 relevant Playwright tests pass  |
| Full repository checks            | Complete | `make check` passed                |

## Risk controls

- Migrate shared primitives before consumers and type-check each slice.
- Preserve specialized controls through an explicit test allowlist.
- Keep dialog calls Promise-based so current control flow and request payloads
  remain unchanged.
- Do not mark a phase verified without fresh command output.

## Verification evidence

- `npm run type-check`: passed.
- `npm run build`: passed after the final visible-heading change.
- `npm run test:unit`: 35 tests passed.
- Relevant Playwright suite: 10 tests passed, covering tables, print, finance,
  payroll, shifts, attendance, inventory, dialogs and a 390 px route crawl.
- Static audit: no native confirm/prompt calls; no direct page tables; native
  page buttons remain only for the specialized report tablist.
- `make check`: passed, including PHPStan, Prettier, Pint, Composer/npm audits,
  platform validation, type-check, production build, 35 Vitest tests and 612
  Pest tests (14,038 assertions).
