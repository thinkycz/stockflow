# Inventory draft autosave verification

## Evidence

- Exact three-decimal input saves unchanged.
- A stale reason is replaced with the classification matching the current
  server-side difference.
- `1.2345` saves as canonical quantity `1.235`.
- A note longer than 2000 characters is persisted.
- Structural validation and authorization remain in the controller/service.
- Complete inventory controller/service suite: 44 tests / 203 assertions
  passed.
- Full backend suite: 486 tests / 9362 assertions passed with the documented
  512 MB PHP memory limit.
- PHPStan level max and formatting passed.
