# Quick shift-adding mode

## Goal

Let an admin configure store-specific shift presets and assign a selected preset to a selected employee by clicking dates in the `/shifts` monthly calendar.

## Requirements

- Presets belong to the active store and contain a unique name, start time, and end time.
- Preset times use the existing same-day quarter-hour rules. Existing stores receive no default presets.
- Admins manage presets in a modal on `/shifts`; limited users remain read-only.
- Quick-add is an explicit mode. A current-month day click persists immediately, including on past dates.
- Repeating the same employee/date/time assignment is an idempotent no-op.
- Overlapping shifts for the same employee, store, and date require explicit confirmation. Adjacent shifts are allowed.
- The overlap rule applies to quick-add, ordinary creation, and editing.
- Assigned shifts snapshot their times and hourly rate; later preset edits or deletion do not change them.
- Calendar entries remain employee-colored, and the public calendar contract does not change.

## Contract

- `/shifts` exposes active-store presets to admins as `{ id, name, start_time, end_time }`.
- `POST /shifts/quick-add` accepts `worker_id`, `shift_preset_id`, `date`, and optional `allow_overlap`.
- Quick-add returns `201 created`, `200 exists`, or `409 overlap` with conflict details.
- Ordinary create/update accepts optional `allow_overlap` and otherwise reports an overlap validation error for confirmation.

## Source

Approved implementation plan from the 2026-07-19 planning session. No external design artifact was provided.
