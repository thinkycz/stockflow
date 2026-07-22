# Limited Inventory Session Items Verification

## Claim

A limited user opening an inventory-history detail for their assigned store sees the inventory's recorded item rows.

## Original Reproduction

The strengthened feature test failed before the production change because the Inertia `rows` prop had size 0 instead of 1 for an admin-owned item and a limited viewer.

## Verification Evidence

- The focused show-controller suite passed: 4 tests, 43 assertions.
- The surrounding inventory-count controller suite passed: 28 tests, 161 assertions.
- `make fix` passed Prettier and Pint.
- `make check` passed PHPStan at the configured maximum level, formatting checks, dependency audits, frontend type-check and production build, unit tests, and the full Pest suite.
- The full Pest suite passed: 528 tests, 10,677 assertions.

## Authorization Regression Check

The surrounding suite confirms that a limited user is still forbidden from opening an inventory session belonging to a different store, while another user's session remains hidden.

## Verdict

Verified. The original empty-row behavior is covered by a regression test and the complete project validation passes.
