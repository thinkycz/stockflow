# Quick shift-adding implementation plan

> Source: [quick shift-adding spec](../specs/2026-07-19-quick-shift-adding.md)

## Phase 1: Preset configuration

- [x] Add the store-scoped preset migration, model, factory, validation, and model coverage.
- [x] Add admin-only preset CRUD routes/controllers and feature coverage.
- [x] Expose active-store presets from the shift index.

## Phase 2: Assignment rules and quick-add contract

- [x] Centralize exact-match and overlap queries.
- [x] Apply overlap confirmation to ordinary create and update.
- [x] Add the quick-add JSON endpoint with created, exists, and overlap responses.

## Phase 3: Calendar workflow

- [x] Add preset management and explicit quick-add controls to `/shifts`.
- [x] Add immediate per-day feedback, conflict confirmation, and local calendar/summary updates.
- [x] Add Czech, English, and Slovak translations.

## Phase 4: Verification

- [x] Run focused backend tests and frontend type/build checks.
- [x] Run repository formatting and validation gates.
- [x] Record requirement-level verification evidence and remaining risks.
