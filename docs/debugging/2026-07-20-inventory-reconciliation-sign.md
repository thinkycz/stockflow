# Inventory reconciliation sign display

## Symptom

Inventory reconciliations looked identical in the movement list and detail
whether the physical count added or removed stock. The displayed quantity and
value had no sign.

## Root cause

The ledger stored both directions correctly in `quantity_difference`, but the
movement UI rendered the absolute `quantity`, row `total` and movement
`total_value` fields. Those fields intentionally preserve movement volume and
historical cost magnitude, so they cannot communicate reconciliation direction.

## Fix

Inventory reconciliation now uses the same before/after/difference layout as a
manual adjustment. The difference formatter uses an explicit plus sign for
positive values, keeps the minus sign for negative values and preserves up to
three decimal places. The reason column uses the inventory row classification.
Each row derives a signed value from `quantity_difference`, and the movement
derives a net value by summing those signed row values. Both list and detail
render that net value with an explicit sign.

## Recurrence prevention

Signed stock changes must render from `quantity_difference`; the absolute
`quantity`, `total` and `total_value` fields are only suitable for movement
volume and immutable valuation snapshots.
