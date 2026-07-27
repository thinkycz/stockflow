# Historical shift rate debugging

## Symptom

Changing a worker's hourly rate changes the calculated salary of their historical shifts.

## Evidence

- `shifts` stores the worker, date, and times, but no hourly-rate snapshot.
- `ShiftIndexController` calculates salary using `Worker::getHourlyRate()`, which always returns the worker's current rate.
- Therefore every historical shift is repriced whenever the worker record changes.

## Root cause

The shift/payroll data model references mutable worker pricing instead of snapshotting the rate used by each shift.

## Fix strategy

- Add an hourly-rate snapshot to every shift and backfill existing rows from the current worker rate.
- Copy the selected worker's current rate when a shift is created.
- Preserve the snapshot when only the date or times are edited; refresh it only when the assigned worker changes.
- Sum salary from each shift's stored duration and rate rather than the current worker rate.

## Resolution

Shifts now own an `hourly_rate` snapshot. Creation copies the selected worker's current rate, ordinary shift edits preserve it, worker reassignment refreshes it from the replacement worker, and monthly salary sums each shift using its own snapshot.

A repository-wide search confirmed that the mutable hourly-rate reference was isolated to shift payroll. Existing inventory valuation intentionally represents current inventory value and is outside this historical-payroll defect.

## Historical data limitation

No previous hourly-rate history exists in the database. The migration can backfill existing shifts only from each worker's current rate. The local existing shift currently resolves to `130.00`; recovering an earlier amount requires the user to supply it.
