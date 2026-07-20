# Inventory draft autosave validation failure

## Symptom

Inventory rows displayed the generic `Chyba ukládání` state during autosave.
The draft could then not be closed because the frontend correctly waited for
all row saves.

## Root cause

Draft autosave applied closing-style business validation too early:

- the reason selected from the browser's displayed stock was rejected when
  the server's current expected stock changed the difference direction;
- quantities with more than three decimal places were rejected instead of
  normalized to the canonical stock precision;
- free-form notes were rejected at 2000 characters despite the database using
  a text column.

The first case is especially important because movements may legitimately
occur between rendering the draft and counting an individual row.

## Fix

Draft autosave now derives a safe default reason whenever the submitted reason
is unknown or no longer matches the server-calculated difference. User-entered
quantities are rounded half-up to three decimal places, and draft notes no
longer have an application-level length limit.

Authorization, item ownership, draft state, non-negative quantities and
monotonic client versions remain enforced. Empty rows remain untouched.

## Recurrence prevention

Draft persistence must normalize recoverable user input. Validation that can
be affected by concurrent stock changes must not make autosave fail.
