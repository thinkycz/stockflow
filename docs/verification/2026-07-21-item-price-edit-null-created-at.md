# Item price edit null `created_at` verification

## Scope Verified

- An item with a null `created_at` can have its purchase price updated.
- The update redirects to the item detail route without the prior 500 response.
- The detail response contains the updated purchase price.
- Movement-history timestamps remain part of the detail response.
- The removed item-level timestamp had no rendered frontend consumer.
- Existing movement rows retain their stored unit cost, row total, and movement
  total after an item price edit.
- A movement created after the edit snapshots the new price.
- Per-item historical consumption value in statistics uses stored movement
  totals, while current inventory valuation continues to use the current price.

## Commands Run

- Focused regression before the fix — failed with the reported
  `assertCarbon("created_at")` exception and HTTP 500.
- Focused regression after the fix — 1 test passed with 4 assertions.
- `make fix` — Prettier and Pint passed.
- `make check` — PHPStan, Prettier, and Pint passed; the command then stopped
  at `composer audit` because the locked Guzzle version has four newly reported
  medium-severity advisories.
- Focused price-history and statistics regressions — 8 tests with 32 assertions
  passed.
- `make stan lint frontend test-unit` — PHPStan, formatting checks, type-check,
  production build, and 14 Vitest tests passed.
- `make test` — 492 Pest tests with 9,392 assertions passed.
- `composer check-platform-reqs` — passed.
- `composer validate --strict --no-check-all` — passed.
- `git diff --check` — passed.

## Runtime Checks

The feature regressions exercise both complete server-side lifecycles: editing
the price on a null-timestamp item and editing a priced item between two stock
receipts. They assert that the first receipt remains valued at its original
snapshot, while the second receipt uses the updated price. A statistics request
also verifies that historical consumption remains valued at its stored total.

## Coverage Summary

Regression coverage lives in `ItemEditControllerTest` and
`StatisticsControllerTest`. The full PHP suite and frontend build/type-check
also pass.

## Known Gaps

The aggregate `make check` command is not fully green because `composer audit`
reports four Guzzle advisories affecting the repository's existing locked
dependency. This is independent of the item fix and was not changed in this
scope.

## Final Status

The reported item price-edit failure is verified fixed. Dependency audit remains
a separate repository-level blocker.
