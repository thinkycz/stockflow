# Store detail latest inventory timestamp failure

## Symptom

Opening a store detail with at least one closed inventory failed in production
with `Undefined property: stdClass::$counted_at)` from Laravel query builder
`pluckFromObjectColumn()`.

## Evidence and hypothesis

`StoreShowController` passed the unaliased expression
`MAX(inventory_sessions.counted_at)` directly to `pluck()`. Laravel strips a
qualified pluck column at the final dot, derives `counted_at)` as the object
property name, and cannot find that property in the database result.

The empty-inventory tests did not exercise Laravel's row-to-property mapping,
so the defect remained hidden until production returned at least one row.

## Globalization check

Repository search found the same unaliased aggregate `pluck()` pattern in the
store list. Its unqualified expression did not reproduce this exact failure on
SQLite, but it relied on a driver-generated result property name and was liable
to fail across database drivers. Both queries share the same root cause class.

## Fix

Both queries now select the aggregate as `last_counted_at` and pluck that stable
alias. The store-detail regression test creates a real closed inventory row,
which exercises Laravel's non-empty row-to-property mapping.
