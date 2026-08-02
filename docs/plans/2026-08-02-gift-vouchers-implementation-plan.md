# Gift Vouchers Implementation Plan

## Phase 1 — Domain and persistence

- Add voucher settings, batches, vouchers, and immutable event audit records.
- Add factories and focused service tests for generation, expiration, and lifecycle transitions.
- Enforce company/store boundaries and concurrency inside one domain service.

## Phase 2 — HTTP surface and printing

- Add authenticated lookup/redemption routes and admin-only management routes.
- Add short-lived session lookup tickets, validation, throttling, branding assets, and SVG QR generation.
- Add feature tests for roles, isolation, state conflicts, and print selection.

## Phase 3 — Inertia UI

- Add one role-aware page with Redeem, Overview, Issue, and Settings views.
- Add batch detail, audited admin actions, exact-code search, filters, and responsive tables.
- Add the portrait A4 print page with explicit three-up sheets and print readiness handling.

## Phase 4 — Product verification

- Complete Czech, English, and Slovak translations and update architecture/application docs.
- Run focused PHP and frontend checks, the complete repository gate, and a browser/print smoke.
- Record exact commands, results, and any remaining evidence gaps.
