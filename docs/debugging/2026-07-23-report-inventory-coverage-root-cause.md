# Report Inventory Coverage Root Cause

## Symptom

Report generation threw a type error when parsing a null inventory session
count timestamp.

## Root Cause

`StatementService::inventoryCoverage()` included open inventory drafts. Drafts
validly have `counted_at = null`, but the coverage loop requires a finalized end
timestamp.

## Why It Happened

The inventory draft workflow later made `counted_at` nullable, while the older
report query retained its original non-null assumption.

## Fix Chosen

Restrict both inventory coverage rows and the last-inventory timestamp to
sessions whose status is `closed`.

## Regression Protection

The statement service test suite now includes an open draft with a saved row and
asserts that it is excluded from report coverage.
