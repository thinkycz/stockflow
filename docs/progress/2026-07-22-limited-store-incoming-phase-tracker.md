# Limited Store Incoming Phase Tracker

## Status

- Current phase: Phase 3 — verification
- Overall status: verified
- Last updated: 2026-07-22

## Phase 1: Authorization and persistence

- Goal: safely support assigned-store incoming movements for limited users.
- Status: completed
- Tasks:
    - [x] Add failing success-path feature test.
    - [x] Add failing cross-store authorization test.
    - [x] Implement explicit incoming mode and service authorization.
- Blockers: none.

## Phase 2: Limited-user interface

- Goal: expose a focused receipt form and navigation entry.
- Status: completed
- Tasks:
    - [x] Add sidebar entry.
    - [x] Adapt the shared form.
    - [x] Add all locale strings.
- Blockers: none.

## Phase 3: Verification

- Goal: prove the complete slice and surrounding behavior.
- Status: completed
- Tasks:
    - [x] Run focused tests.
    - [x] Run `make fix` and `make check`.
- Blockers: none.

## Decisions

- Reuse `StockMovementService` and the existing `incoming` ledger type.
- Use an explicit UI/request mode named `incoming` while preserving admin's inferred transfer form.
- Pin limited users to `assigned_store_id` on both read and write paths.

## Deferred

- None.

## Next

- Ready for handoff.
