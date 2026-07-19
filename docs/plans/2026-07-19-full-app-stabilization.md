# Full application stabilization

## Goal

Resolve the confirmed full-app audit findings in three independently
verifiable stages: account and numbering safety, immutable ledger and unified
analytics, then decimal quantities and UX/performance cleanup.

## Locked decisions

- One company and exactly one main admin per deployment.
- Public registration is removed; `UserSeeder` idempotently provisions
  `test@test.com` / `password` when the database has no users.
- Orphan root accounts and their owned data migrate under the main admin.
- Every posted manual ledger event is reversed, never deleted.
- Inventory is a resumable one-draft-per-store workflow with per-row autosave
  timestamps.
- One stock unit per item, quantities at three decimal places, standard unit
  cost at four decimal places, money at two decimal places.
- Inventory-derived consumption is prorated at period boundaries and all
  analytics use one calculation service.

## Stages

1. Remove registration, enforce the single-company account model, repair
   numbering invariants, and make limited-user consumption usable end to end.
2. Introduce reversals, unit-cost snapshots, inventory drafts, unified
   analytics, stable locking, and a genuinely batched historical backfill.
3. Migrate quantities through expand/backfill/switch/contract, remove mixed-unit
   totals, batch analytics queries, repair statement versioning, localize dates,
   and update documentation.

## Verification gates

- Each stage has focused backend and frontend regression coverage.
- MySQL-specific invariants and lock behavior receive an integration test path.
- Final handoff requires `make fix`, `make check`, frontend build/type-check,
  relevant browser E2E, and documented production dry-runs.
