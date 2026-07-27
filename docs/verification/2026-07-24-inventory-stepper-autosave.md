# Inventory stepper autosave verification

## Claim

Inventory quantities changed through the plus and minus buttons use the same
draft autosave path as manually entered quantities and survive a page reload.

## Evidence

- Before the fix, the Playwright regression timed out waiting for the
  draft-row `PUT` after clicking plus.
- After the fix, the same browser scenario observed `PUT` responses for plus
  and minus, reloaded after each direction, and retained quantities `2` and
  `1` with the expected classifications.
- The targeted Playwright test passed.
- `make fix`, `npm run type-check`, and the production build passed.
- Vitest passed: 6 files and 14 tests.
- `InventoryDraftRowControllerTest` passed: 4 tests and 12 assertions.

## Gap

External Composer/npm dependency audits were not repeated because transmitting
dependency metadata to public audit services was not authorized. The full PHP
suite had passed immediately before this frontend-only follow-up change.

## Recurrence prevention

Every inventory editor interaction that mutates a draft row must either invoke
`autosave` directly or have an explicit user event that does so. The browser
regression now asserts the network request and persisted state rather than only
checking local UI state.
