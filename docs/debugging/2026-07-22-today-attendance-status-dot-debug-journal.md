# Today Attendance Status Dot Debug Journal

## Symptom

The status dot for completed rows in the "Today's attendance" panel is invisible.

## Reproduction

Render a completed attendance session in the today's-attendance table. The dot element has dimensions and rounded shape but no visible background.

## Evidence

- Working rows use `bg-emerald-500` and break rows use `bg-amber-400`.
- Completed rows use `bg-outline`.
- The Tailwind theme defines `--color-outline-glass`, but does not define `--color-outline`.
- The current production CSS contains no generated `.bg-outline` utility.
- The theme already defines the semantic neutral color `--color-neutral: #475569`.

## Working Theory

Tailwind cannot generate `bg-outline` because the corresponding theme token does not exist, leaving the completed-state dot transparent.

## Globalization Check

`bg-outline` occurs only in this attendance status dot. Other outline usages consistently use the valid `outline-glass` token for borders and subtle surfaces, so the defect is local.

## Proven Root Cause

The completed-state branch references a nonexistent Tailwind color utility.

## Fix

Use the existing semantic `bg-neutral` token for the completed-state dot. It is valid and provides sufficient contrast against the white table surface.

## Next Probe

Build the frontend and confirm that Tailwind emits `.bg-neutral`, then run the project checks.
