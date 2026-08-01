# Attendance row actions implementation

1. Add a failing controller contract for scheduled, active unscheduled,
   excluded inactive and grouped rows with monthly quality scores.
2. Build the attendance overview payload with bulk-loaded shifts, sessions and
   breaks.
3. Replace the selector panel and card-wrapped pseudo-table with a standalone
   `DataTable`, row actions and off-schedule arrival dialog.
4. Add frontend contracts, translations and deterministic Playwright coverage.
5. Run focused checks, browser verification and `make check`.
