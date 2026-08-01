# Attendance row actions progress

| Requirement                     | Status   | Evidence                                 |
| ------------------------------- | -------- | ---------------------------------------- |
| Attendance overview payload     | Complete | Controller feature tests                 |
| Standalone actionable table     | Complete | `attendance/Index.vue` and unit contract |
| Monthly quality indicator       | Complete | Rating payload, localized icon and score |
| Off-schedule arrival dialog     | Complete | Filtered payload and browser test        |
| Mobile and browser verification | Complete | Four targeted Playwright scenarios       |
| Full repository checks          | Complete | `make fix` and `make check` passed       |
| Timer overview panel            | Complete | Restored above the standalone table      |

## Constraints

- Preserve the current dirty worktree and existing attendance route contract.
- Keep specialized state transitions in `AttendanceService`.
- Avoid per-worker queries when building the page payload.

## Runtime evidence

- The scheduled-worker browser flow completed arrival, break start, return,
  and departure directly from one table row.
- The off-schedule dialog excluded scheduled and actively working employees.
- At 390 px, the row used the DataTable card layout and the document had no
  horizontal overflow.
