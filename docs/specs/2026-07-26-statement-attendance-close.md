# Statement Attendance Closure

## Source

Approved user plan from 2026-07-26.

## Requirements

- For all authenticated users, expose every active attendance session started on the current Prague business day at the authorized statement store.
- Before saving today's statement through quick entry or the current-month grid, show a modal listing the active workers with status indicators and live worked-time timers that exclude breaks.
- Let the user save without closing attendance or save and close every eligible attendance.
- When closure is requested, reload and lock the server-authoritative eligible set and save the statement plus all departures atomically.
- Preserve existing attendance break closure, audits, and operational notifications.
- Admins participate for their selected owned store; historical statement saves must not participate in the attendance-close flow.

## Decisions

- Stale sessions from previous business days remain open for administrator correction.
- The browser-provided employee list is informational; the server determines the final closure set.
- Dismissing the modal cancels the pending save.
