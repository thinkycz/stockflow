# Inventory reconciliation sign display

## Symptom

Inventory reconciliations looked identical in stock movement detail whether
the physical count added or removed stock. The displayed quantity had no sign.

## Root cause

The ledger stored both directions correctly in `quantity_difference`, but the
movement detail rendered only manual adjustments with the before/after/
difference table. Inventory reconciliation fell through to the generic table,
which intentionally displays the absolute `quantity` field.

## Fix

Inventory reconciliation now uses the same before/after/difference layout as a
manual adjustment. The difference formatter uses an explicit plus sign for
positive values, keeps the minus sign for negative values and preserves up to
three decimal places. The reason column uses the inventory row classification.

## Recurrence prevention

Signed stock changes must render from `quantity_difference`; the absolute
`quantity` field is only suitable for movement volume.
