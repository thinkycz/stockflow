# Full audit remediation

## Objective

Harden StockFlow against the confirmed security, data-loss, tenant-isolation,
financial-consistency, async-recovery, UI, localization, and release-gate bugs
found in the 2026-09-02 audit. The implementation follows the approved plan;
failed bank imports remain uniqueness-bearing and malformed financial values are
never silently rounded.

## Delivery order

1. Authentication, password recovery, CORS, and production provisioning.
2. Store/worker lifecycle safety and limited-user item projections.
3. Gift-voucher and bank-review UI feedback plus backend localization parity.
4. Exact payroll money arithmetic and transactional lifecycle locks.
5. Recoverable bank-import dispatch, timeout, maintenance, and month scoping.
6. Isolated browser checks, pinned CI inputs, MySQL invariants, and closeout.

## Decisions

- Keep both cookie and bearer API authentication; protect cookie mutations with
  a double-submit CSRF token.
- Provision production administrators only through a hidden-input CLI command.
- Archive historically referenced stores/workers, hard-delete only pristine
  records, and block archival while live work remains.
- Run the complete isolated Playwright suite from `make check`.
- Keep AI work on a dedicated `assistant` worker.
- Retry failed bank imports in place; do not weaken the logical uniqueness rule.
- Accept harmless surrounding whitespace in parsed money, but require exact
  two-decimal precision and classify invalid payloads without rounding.

## Completion gate

Every tracker row must be `verified`, the full canonical check must pass, the
MySQL-only invariants must pass in CI, and the verification report must record
any deployment-only evidence that cannot be produced locally.
