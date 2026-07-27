# Inventory cancel modal and plus reason verification

## Acceptance checks

- A plus click resulting in a quantity above current stock selects `inventory_correction`.
- A successful cancellation closes its confirmation modal.
- A failed cancellation leaves the modal available for retry.
- Type checking, formatting, build, translations, backend authorization tests, and the full test suite remain green.

## Evidence

- `npm run type-check` passed after the UI state changes.
- `InventoryDraftCancelControllerTest` passed: 3 tests and 9 assertions, including assigned-store success and foreign-store denial.
- `make fix` passed.
- `make check` passed: PHPStan, formatting, audits, type checking, production build, 14 frontend unit tests, and 544 PHP tests with 10,791 assertions.

The local application hostname was not available in this environment, so the two click interactions were not browser-smoke-tested here. Their event paths are covered by the production TypeScript build and the cancellation endpoint is covered by feature tests.
