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
- Wage rows use each worker's final payroll amount (planned-shift base plus
  payroll tips and deductions). Financial wage overrides remain supported.
- Automatic rows expose their calculated value and can receive a persistent
  effective-value override.
- Manual income and expense rows have a label, date, amount, and optional note.
- Manual rows can be copied idempotently from the previous month.
- Recurring expenses are store-scoped schedules with monthly effective versions,
  a due day, fixed CZK amount, optional note, and an exclusive ending month.
- Each applicable recurring expense produces one live automatic expense row.
  Changes affect the selected month onward without rewriting earlier versions;
  one month can still use the standard automatic-row override.
- Ending a recurring expense preserves its history and prevents occurrences from
  the selected ending month onward. Recurring rows are never copied as manual rows.
- Closing a month freezes a complete snapshot and locks mutations. Reopening
  restores live source calculations while preserving overrides and manual rows.
- Closing requires the matching payroll report to be closed first.
- The warehouse has no report; limited users have no navigation or route access.
- All money is CZK with two-decimal output; VAT is not calculated separately.

## Acceptance

The page shows separate income and expense tables plus total income, total
expenses, and profit. Source changes affect open reports unless overridden,
closed reports remain unchanged, and reopened reports refresh from sources.
Recurring-expense management remains available while a closed month is viewed,
but its saved snapshot cannot change until the report is reopened.
