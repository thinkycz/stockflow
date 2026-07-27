# Public shift calendar

## Source

User request confirmed on 2026-07-17.

## Goal

Allow an administrator to copy a public, unauthenticated link for the active
store so employees can view the entire store calendar, including other
workers' names and shifts.

## Requirements

- The share link belongs to a store, not an individual worker.
- The URL contains an unguessable persistent token and exposes only the store
  associated with that token.
- The public page requires no authentication.
- The public page is read-only and contains no create, update, or delete UI.
- The calendar supports month navigation and shows worker names, dates, and
  start/end times.
- The authenticated shift page provides a button that creates or reuses the
  active store link and copies it to the clipboard.
- Only administrators can create or retrieve a share link.
- Frontend translation keys remain in parity across Czech, English, and Slovak.

## Decisions

- Share links are persistent and reused on subsequent copy actions.
- Token rotation and revocation are deferred because they were not requested.
- Public pages use a standalone task-focused layout without authenticated
  navigation or editing controls.
