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

## Phase 4: Multi-link management and revocation

- [x] Replace the store token with migrated `shift_share_links` records.
- [x] Add admin-only named link creation and store-scoped deletion actions.
- [x] Resolve all public calendar, manifest, and request routes through active
      link records so deletion revokes every public surface.
- [x] Replace the copy button with a management modal using shared UI and
      confirmation primitives.
- [x] Cover multi-link creation, validation, authorization, isolation, legacy
      fallback naming, and revocation with feature tests.
- [x] Run formatting, PHPStan, lint, frontend build/type-check, unit tests,
      focused feature tests, and the full Pest suite.
- [ ] Resolve repository dependency advisories before `make check` can pass:
      `league/commonmark <2.9.0` and `nanoid <3.3.17`.
