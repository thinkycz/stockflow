# Mobile store switch stale-data verification

## Claim

Switching stores from the mobile navigation refreshes the current page with
the newly selected store, including when the current URL contains a stale
`store_id` override.

## Automated evidence

- `npm run test:unit -- --run tests/Unit/store-switch-navigation.test.ts`
  passed: 2 tests.
- `npm run test:unit` passed: 2 files, 6 tests.
- `npm run type-check` passed.
- `npm run build` passed.
- `php artisan test tests/Feature/App/Http/Controllers/Web/Store/StoreSwitchControllerTest.php`
  passed: 6 tests, 13 assertions.

## Browser evidence

Verified against a freshly migrated SQLite testing database with the app at a
mobile viewport:

1. Opened `/dashboard?store_id=1`, which rendered `Warehouse`.
2. Opened the mobile drawer and selected `Mobile Retail`.
3. The switch request completed and the Inertia GET navigated to `/dashboard`
   without the stale query override.
4. The dashboard rendered `Mobile Retail`.
5. No browser console errors were recorded.

## Verdict

Verified. The original stale-query path no longer reproduces, page state is
rebuilt for the selected store, and the focused frontend checks pass.
