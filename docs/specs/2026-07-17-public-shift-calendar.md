# Public shift calendar

## Source

User request confirmed on 2026-07-17.

## Goal

Allow an administrator to manage named public, unauthenticated links for the
active store so employees can view the entire store calendar, including other
workers' names and shifts, while individual links can be revoked.

## Requirements

- The share link belongs to a store, not an individual worker.
- The URL contains an unguessable persistent token and exposes only the store
  associated with that token.
- The public page requires no authentication.
- The public page is read-only and contains no create, update, or delete UI.
- The calendar supports month navigation and shows worker names, dates, and
  start/end times.
- The authenticated shift page provides an administrator-only management modal
  for creating, copying, and deleting named store links.
- A store may have any number of active links, each with its own token.
- Deleting a link immediately invalidates its calendar, manifest, and request
  endpoints without affecting other links.
- Only administrators can list, create, copy, or delete share links.
- Frontend translation keys remain in parity across Czech, English, and Slovak.

## Decisions

- New links require a store-unique name and receive a new 64-character token.
- Existing store tokens are migrated without changing their public URL and use
  a localized "Original public link" fallback name.
- Token rotation is represented by creating a replacement and deleting the old
  link; deleting the last link is allowed.
- Public pages use a standalone task-focused layout without authenticated
  navigation or editing controls.
