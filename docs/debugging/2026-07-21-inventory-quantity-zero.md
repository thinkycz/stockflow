# Inventory quantity displayed as zero

## Symptom

The quantity column on the inventory list displays `0` for stocked items.

## Reproduction

Store a decimal quantity for an item at the active store and request the item
index.

## Evidence

- The inventory list selects `store_items.quantity` into
  `active_store_quantity`.
- Migration `2026_07_19_000006_switch_quantities_to_decimal.php` changed the
  production column to `DECIMAL(18,3)`.
- MySQL returns decimal columns as numeric strings such as `7.000`.
- `ItemIndexController` passes that value to `Typer::parseInt()`.
- `Typer::parseInt()` returns `0` when the input is not a valid integer; decimal
  strings and fractional floats therefore collapse to zero.
- `StoreItem::getQuantity()` and `ItemSearchController` already preserve decimal
  quantities as `float|int`.

## Working Theory

The inventory index retained integer-only parsing after quantities became
decimal. It should normalize the database scalar using the same whole-number or
fractional-number behavior as `StoreItem::getQuantity()`.

## Globalization Check

Repository search found the bad `parseInt(active_store_quantity)` conversion
only in `ItemIndexController`. Item search and store detail use decimal-aware
conversion and do not share this defect.

## Reproduction Result

The focused feature test stored `0.5` at the active store. Before the fix, the
inventory response returned `0`, proving the integer parser caused the symptom.

## Root Cause

`ItemIndexController` continued to use `Typer::parseInt()` after quantities were
migrated to decimal. Invalid integer inputs are intentionally converted to zero
by that parser, so production decimal strings lost their values.

## Fix

The initial fix made the active-store conversion decimal-aware.

## Follow-up Requirement

The intended inventory semantics were subsequently clarified: the inventory
list and item detail must show data from all stores, not only the active store.

New failing tests proved that:

- the inventory list exposed only `store_quantity`, not the sum of all stores;
- item detail movement history excluded movements outside the active store;
- the item summary included an active-store quantity in its payload.

## Final Resolution

- The inventory list now uses the existing `total_quantity_sum` aggregate and
  exposes `total_quantity` for every row.
- Decimal totals remain preserved across multiple stores.
- Item detail shows the total quantity across all stores.
- Item detail movement history includes movements from every store.
- The active store is retained only for visual highlighting in the per-store
  breakdown.
- Labels explicitly say “Total quantity” in Czech, English, and Slovak.
