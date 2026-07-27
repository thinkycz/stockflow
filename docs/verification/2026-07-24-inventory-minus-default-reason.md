# Inventory minus default reason verification

## Claim

When the inventory minus button makes a row's difference negative, the default
reason is `consumption`, including after a persisted draft reason was previously
overridden by the plus button.

## Evidence

- The Playwright regression failed before the fix: the negative-difference
  reason select had an empty value because the row still held
  `inventory_correction`.
- After building the updated frontend, the same Playwright scenario passed.
- `make fix` passed.
- PHPStan, Prettier check, and Pint check passed as the first stages of
  `make check`.
- `npm run type-check` and `npm run build` passed.
- Vitest passed: 6 files and 14 tests.
- Pest passed: 553 tests and 10,881 assertions.

## Gap

The external dependency audit stage of `make check` was not run because network
access to Packagist/npm audit services was not authorized. This does not affect
the runtime regression evidence above.

## Recurrence prevention

Stepper presets for positive and negative inventory differences must remain
symmetrical. The browser regression now covers a persisted draft crossing both
sign boundaries.
