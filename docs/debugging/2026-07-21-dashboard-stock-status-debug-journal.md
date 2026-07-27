# Dashboard Stock Status Debug Journal

## Symptom

The dashboard stock-status card does not show every availability category correctly.

## Reproduction

Open the dashboard with an active store and compare the `Monthly Flow` and `Stock Status` cards.

## Evidence

- `DashboardController` returns all four categories: `in_stock`, `low_stock`, `out_of_stock`, and `no_data`.
- The same shared inventory prediction service is used by the store and statistics screens.
- `Dashboard.vue` renders the `no_data` row inside the `Monthly Flow` card instead of the `Stock Status` card.

## Root Cause

The `no_data` markup was inserted between the purchases and consumption tiles in the monthly-flow grid. The stock-status card therefore omitted the category while the monthly-flow layout gained an unrelated third row.

## Globalization Check

The underlying prediction service and other inventory screens use the category correctly. The defect is local to the dashboard template.

## Fix Direction

Move the existing `no_data` row into the stock-status card and protect its card ownership with a browser regression test.

## Resolution

The category now renders in the stock-status card with its count, percentage, and progress bar. The monthly-flow card contains only purchases and consumption.
