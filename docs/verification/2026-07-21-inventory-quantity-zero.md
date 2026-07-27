# Inventory quantity displayed as zero verification

## Scope Verified

- The inventory list sums quantities across every store.
- Decimal totals are preserved across multiple stores.
- The item detail summary shows the total across every store.
- The item detail movement history includes movements from every store.
- The per-store breakdown still lists every store and may highlight the active
  store without filtering the page.
- Czech, English, and Slovak labels identify the aggregate as total quantity.

## Commands Run

- Inventory-list tests before the all-store change — 3 tests failed because the
  response exposed only active-store quantity.
- Item-detail tests before the all-store change — 2 tests failed because
  movement history was filtered and active-store quantity remained exposed.
- `php artisan test tests/Feature/App/Http/Controllers/Web/Item/ItemIndexControllerTest.php`
  — 6 tests and 27 assertions passed.
- Focused item index and show suites — 9 tests and 40 assertions passed.
- `make fix` — Prettier and Pint passed.
- `make stan lint frontend test-unit test` — PHPStan, formatting checks,
  TypeScript, production build, 14 Vitest tests, and 490 Pest tests with 9,381
  assertions passed.

## Runtime Checks

Feature tests request the real Inertia inventory endpoints with quantities and
movements split between a warehouse and retail store. They assert the inventory
and detail totals equal the sum and that both stores' movements are returned.

## Coverage Summary

Regression coverage includes whole and decimal totals, multi-store movement
history, per-store breakdown payloads, search, pagination, and response shape.

## Known Gaps

The repository's aggregate `make check` remains unable to pass its audit stage
because the locked Guzzle version has four pre-existing medium-severity
advisories. All non-audit validation targets passed.

## Final Status

Verified fixed.
