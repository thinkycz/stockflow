# Historical shift rate verification

## Claim

Changing a worker's hourly rate does not reprice existing shifts or their monthly salary.

## Evidence

- A shift created at `200.50` retains `200.50` after its worker changes to `350.00`.
- A monthly fixture containing 8.5 hours at `200.50` remains `1,704.25` after its worker changes to `999.00`.
- Editing only a shift's date or times preserves its snapshotted rate.
- Reassigning a shift to a worker at `350.00` snapshots `350.00`.
- The migration adds the required snapshot column and backfills pre-existing rows from current worker rates.
- Related shift tests, relevant architecture checks, PHPStan, frontend unit tests, type-checking, production build, formatting, and diff checks pass.

## Remaining limitation

Rates from before this feature cannot be reconstructed automatically because they were never persisted. Existing rows are backfilled using the current worker rate.

## Recurrence prevention

Historical financial calculations must use immutable transaction or assignment snapshots rather than mutable master-record prices.
