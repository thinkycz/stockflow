# Unified Reports Specification

## Goal

Replace the separate financial Reports and inventory Statistics destinations with one admin-only monthly Reports page.

## Requirements

- `/reports` is the canonical page and uses one active-store and calendar-month context.
- The page shows a shared four-metric summary followed by accessible Finance and Inventory tabs.
- Financial and inventory flows use the same month.
- Historical inventory quantities are reconstructed at the selected month end; the current month uses the current time.
- Historical inventory value uses current purchase prices and is identified as an estimate.
- Inventory forecasts use only observations available at the reporting cutoff.
- `/reports/statistics` redirects to `/reports` and the sidebar exposes only Reports.

## Acceptance

- Backend, frontend, translations, navigation, documentation, and tests describe one reporting surface.
- Existing tenant and admin authorization boundaries remain unchanged.
