# Inventory minus default reason

## Symptom

After the plus button selects `inventory_correction` for a positive inventory
difference, using the minus button until the difference becomes negative can
leave that positive-difference reason selected instead of defaulting to
`consumption`.

## Reproduction

1. Autosave a draft row with a negative difference and reload the inventory
   editor so its saved classification is treated as touched.
2. Use plus until the difference is positive; the reason becomes
   `inventory_correction`.
3. Use minus until the difference is negative.
4. The reason incorrectly remains `inventory_correction`.

## Root cause

`adjustQuantity` explicitly overrides touched draft state for the plus-button
positive path, but has no symmetric override for the minus-button negative
path. The shared `setQuantity` inference intentionally skips touched
classifications, so it cannot correct this transition.

## Scope check

The asymmetry is local to the inventory stepper. Manual quantity entry
continues to preserve a user-selected or persisted reason, while an explicit
stepper direction can apply that direction's default.
