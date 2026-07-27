# Inventory reconciliation sign verification

## Evidence

- Backend detail contract exposes `-3 / consumption` and
  `+2 / inventory_correction`, signed row values `-30 / +20`, and net value
  `-10` in one reconciliation.
- Backend list contract exposes an absolute historical value of `30` alongside
  the signed net value `-30`.
- Signed-number and signed-money unit tests cover both directions and zero.
- Stock quantity formatting preserves decimal values.
- Chromium E2E created counts `0 → 2` and `2 → 1` and verified positive and
  negative CZK values alongside `+2` and `-1` in the movement list and details.
- `make check` passed: PHPStan max, formatting, dependency audits, frontend
  type-check/build, 12 frontend unit tests and 488 backend tests with 9,375
  assertions.
- Complete Playwright suite: 19 Chromium scenarios passed.
