# Store detail latest inventory timestamp verification

## Claim

A store detail containing closed inventory rows loads successfully and exposes
the latest closed inventory timestamp for each item.

## Evidence

- The focused regression reproduced the original 500 response and exact
  `stdClass::$counted_at)` exception before the fix.
- After aliasing the aggregate, store detail and store list controller suites
  pass: 12 tests with 49 assertions.
- The store detail HTTP test now returns 200 with a real closed inventory row
  and exposes its timestamp as `last_count_at`.
- The store list HTTP test exposes the same closed inventory timestamp as
  `last_inventory_at`.
- `make fix` completed successfully.
- `make check` passed PHPStan max, formatting, dependency audits, frontend
  type-check/build, 12 frontend unit tests and 489 backend tests with 9,377
  assertions.

## Verdict

Verified locally. The query-builder failure is removed without a schema or data
migration; deployment of the application code is still required for production.
