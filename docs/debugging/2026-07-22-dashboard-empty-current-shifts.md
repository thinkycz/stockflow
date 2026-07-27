# Dashboard failure with attendance and no current shift

## Symptom

A limited user received HTTP 500 on `GET /dashboard` with `Call to a member function getKey() on int` at `DashboardController.php:400`.

## Evidence

- The failing request had one active attendance session.
- The current-shift query returned no rows.
- `Eloquent\Collection::map()` converts to a base collection only when the mapped result contains a non-model value. For an empty collection it remains an Eloquent collection.
- `Eloquent\Collection::merge()` treats its input as models and calls `getKey()` on every item.

## Root cause

The dashboard mapped an empty Eloquent shift collection to worker IDs and then merged integer attendance worker IDs into it. Because the first collection was empty, the mapped collection retained Eloquent merge semantics and called `getKey()` on an integer.

## Scope check

The same map-then-merge pattern occurs only in the dashboard operations summary. Other ID mappings convert directly to arrays and do not use Eloquent collection merging.

## Fix

Worker IDs are now combined as plain arrays before deduplication. This keeps
scalar IDs out of Eloquent collection merge semantics for both empty and
non-empty shift results. A regression test covers an active attendance session
with no current shift.
