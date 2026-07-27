# Shift summary hours debugging

## Symptom

The admin monthly summary reports an incorrect value for **Naplánované hodiny**.

## Evidence

- The local database contains a shift from `09:00:00` to `16:00:00`.
- Running `Shift::getDurationMinutes()` against that row returned `960` minutes (`16` hours) instead of `420` minutes (`7` hours).
- `Typer::parseInt('09')` is unsuitable for parsing a zero-padded time component, causing the start hour to be treated as zero.

## Root cause

`Shift::getDurationMinutes()` split the SQL time string and passed zero-padded hour and minute components through the generic integer parser. Morning hours with a leading zero were parsed incorrectly.

## Fix strategy

Parse the complete validated SQL time values as times and calculate the minute difference between them. Protect the morning-time case with a model regression test and retain the controller-level payroll summary test.

## Resolution

`Shift::getDurationMinutes()` now parses both `H:i` values used by SQLite tests and `H:i:s` values returned by MySQL. A repository-wide search found no other time calculation using the faulty component parsing pattern.
