# Attendance row actions specification

## Goal

Make the attendance table the primary control surface. It must list every
worker scheduled today plus every worker with an open attendance session and
allow all valid attendance transitions directly from that worker's row.

## Contract

- One row per worker, with today's shifts grouped in the row.
- Scheduled workers sort by first shift start; active unscheduled workers follow
  alphabetically.
- Row actions reflect absent, present, break and stale states.
- The current month's evaluated shifts provide an accessible good, warning,
  poor or unrated quality indicator.
- Off-schedule arrival remains available from a small header dialog.
- The table is standalone and uses the shared mobile-card behavior.

## Invariants

- Existing attendance action route, validation, audit and notification behavior
  remain unchanged.
- Business-day calculations use `Europe/Prague`.
- No database migration is required.
