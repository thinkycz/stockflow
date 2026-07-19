# MySQL user role CHECK verification

## Evidence

- The previously pending migration `2026_07_19_000007` completed against the
  local MySQL database without error 3823.
- `db:table users` reports `ON DELETE RESTRICT` for both
  `users_parent_user_id_foreign` and `users_assigned_store_id_foreign`.
- `information_schema.CHECK_CONSTRAINTS` contains
  `users_role_shape_check` with the required admin/limited role expression.
- Store deletion regression: 5 tests / 11 assertions passed, including the
  assigned-limited-user case.
- Focused PHPStan passed with no errors.
- Playwright regression suite passed: 18 Chromium scenarios.
- `UserSeeder` creates `test@test.com` with the admin role in its initial
  INSERT; no post-insert role-shape transition remains.
- The seeder completed twice against the constrained local MySQL schema and
  retained exactly one valid admin and one warehouse.
- The complete `DatabaseSeeder` then completed successfully, including stores
  and items.
