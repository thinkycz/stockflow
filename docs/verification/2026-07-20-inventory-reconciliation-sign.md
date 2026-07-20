# Inventory reconciliation sign verification

## Evidence

- Backend detail contract exposes `-3 / consumption` and
  `+2 / inventory_correction` in one reconciliation.
- Signed-number unit test covers positive, negative and zero values.
- Stock quantity formatting preserves decimal values.
- Chromium E2E created counts `0 → 2` and `2 → 1` and verified `+2` and `-1`
  in the corresponding movement details.
- Focused controller tests, frontend unit tests, type-check and production
  build passed.
- Complete Playwright suite: 19 Chromium scenarios passed.
