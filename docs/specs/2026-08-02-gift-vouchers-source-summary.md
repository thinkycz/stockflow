# Gift Vouchers Source Summary

## Source

- User-approved Czech implementation plan from 2026-08-02.
- Repository architecture and conventions in `AGENTS.md`, `docs/guidelines.md`, and `docs/architecture.md`.
- No external visual design file was supplied; the existing StockFlow design tokens are the visual source of truth.

## Normalized requirements

- Both roles use one gift-voucher section. Limited users redeem at their assigned retail store; admins redeem at the active retail store and manage the feature.
- Admins configure customer-facing branding, issue batches of 1–100 one-use CZK vouchers, and print three vouchers per portrait A4 page.
- Codes are random, human-readable, QR-encoded, encrypted at rest, and exactly searchable through a unique digest.
- Redemption is a two-step, short-lived session-ticket flow and must be concurrency-safe.
- Vouchers support derived expiration, irreversible voiding, and audited reversal of an erroneous redemption.
- Reprints include only active, unexpired vouchers. Financial statements and POS data remain unchanged.

## Decisions

- Canonical terms: batch, redemption, void, redemption reversal.
- Currency: CZK with two decimal places; redemption always consumes the full value.
- Voucher scope: all retail stores owned by the single company; warehouses are excluded.
- Expiration: optional, through the end of the selected Prague-local day.
- QR content: the normalized voucher code only; no camera scanning in v1.
- Branding: required public name plus optional message/logo, snapshotted per batch.

## Missing artifacts

- No logo, customer brand palette, or voucher mockup was supplied. The implementation uses the existing navy/cyan StockFlow tokens and administrator-provided logo/content.
