# Store-Specific Slack Notifications Phase Tracker

## Status

- Current phase: Phase 4 — verification
- Overall status: verified
- Last updated: 2026-07-22

## Phase 1: Store configuration

- Goal: persist and administer an optional Slack channel per store.
- Status: verified
- Tasks:
    - [x] Add failing persistence and validation tests.
    - [x] Add migration, model getter, validity, and controller mappings.
    - [x] Add create/edit/detail UI and synchronized translations.
    - [x] Verify focused backend and frontend checks.
- Blockers: none.

## Phase 2: Notification infrastructure

- Goal: provide safe queued store-channel Slack delivery.
- Status: verified
- Tasks:
    - [x] Install package and configure the bot token.
    - [x] Add typed event, listener, notification, and activity types.
    - [x] Verify routing, rendering, disabled state, and dispatch failure isolation.
- Blockers: real Slack delivery requires operator credentials; automated implementation is not blocked.

## Phase 3: Domain integration

- Goal: emit exactly the approved operational activities.
- Status: verified
- Tasks:
    - [x] Attendance and corrections.
    - [x] Inventory finalization.
    - [x] Statement mutations.
    - [x] Manual movements and reversals.
    - [x] Exclusion and deduplication coverage.
- Blockers: none.

## Phase 4: Verification

- Goal: prove specification compliance and repository health.
- Status: verified
- Tasks:
    - [x] Run focused tests and frontend checks.
    - [x] Run `make fix` and `make check`.
    - [x] Complete the verification report and traceability matrix.
- Blockers: none.

## Decisions

- Store channels are optional and non-unique.
- Transfers notify both affected configured channels.
- Notification enqueue failures are reported but do not change committed outcomes.

## Deferred

- Operator Slack App provisioning and live credentialed delivery smoke test.

## Next

- Deploy the migration, configure the bot token and store channels, then perform the deferred live Slack smoke test.
