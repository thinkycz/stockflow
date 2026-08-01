# Monthly Store Income and Expenses

## Goal

Add an admin-only monthly financial report for each retail store. The report
combines calculated revenue, incoming stock costs, planned wages, manual rows,
and durable administrator overrides into one auditable result.

## Requirements

- The page is available only to the main admin and uses the active retail store.
- Revenue rows are Cash, Card, Bolt, Wolt, and Foodora. Monthly commission is
  0%, 1%, 30%, 30%, and 30% respectively; Bolt includes `bolt_cash`.
- Each non-reversed incoming receipt or inbound transfer is an expense row.
- Planned wages are aggregated per worker from shift duration and the rate
  snapshotted on each shift.
- Automatic rows expose their calculated value and can receive a persistent
  effective-value override.
- Manual income and expense rows have a label, date, amount, and optional note.
- Manual rows can be copied idempotently from the previous month.
- Closing a month freezes a complete snapshot and locks mutations. Reopening
  restores live source calculations while preserving overrides and manual rows.
- The warehouse has no report; limited users have no navigation or route access.
- All money is CZK with two-decimal output; VAT is not calculated separately.

## Acceptance

The page shows separate income and expense tables plus total income, total
expenses, and profit. Source changes affect open reports unless overridden,
closed reports remain unchanged, and reopened reports refresh from sources.
