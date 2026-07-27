# Public shift calendar implementation plan

> Source: [public shift calendar spec](../specs/2026-07-17-public-shift-calendar.md)

## Phase 1: Share contract and persistence

- [x] Add a nullable unique share token to stores.
- [x] Add explicit Store model APIs for the token.
- [x] Add an admin-only JSON endpoint that creates or reuses the active store
      share URL.
- [x] Cover ownership, token reuse, and authorization with feature tests.

## Phase 2: Public read-only calendar

- [x] Add an unauthenticated token route and Inertia controller.
- [x] Scope shifts and worker names to the token's store owner.
- [x] Add a standalone read-only calendar page with month navigation.
- [x] Cover valid, invalid, and cross-store token behavior with feature tests.

## Phase 3: Authenticated share UX and verification

- [x] Add a copy-link button to the authenticated shift page.
- [x] Add Czech, English, and Slovak translations.
- [x] Run focused tests, architecture tests, type-check, build, formatting, and
      scoped PHPStan.
- [x] Record fresh verification evidence.
