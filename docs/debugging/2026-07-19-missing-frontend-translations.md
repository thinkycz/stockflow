# Missing frontend translation keys

## Symptom

The stores table rendered raw keys such as
`stores.columns.inventory_value` instead of localized labels.

## Root cause

The redesigned stores page introduced five new static translation references,
but none of the three locale catalogs contained them. Existing checks compared
locale catalogs only indirectly, so keys missing from every locale were not
detected.

## Fix

English, Czech and Slovak catalogs now define inventory value, SKU count,
out-of-stock count, seven-day risk and last inventory labels. The i18n unit
suite now verifies both catalog parity and every statically referenced `t()`
key under `resources/js`.

Dynamic keys remain supported; the scanner intentionally considers only
complete string literals and ignores concatenated expressions.

## Recurrence prevention

Any new static frontend translation reference must exist in the English
catalog and all locale catalogs must expose the same flattened key set.
