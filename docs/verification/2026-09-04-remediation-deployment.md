# Remediation deployment and diagnostics

This is a runbook, not evidence of production execution. Production delivery
and historical repairs are separate from local implementation verification.

1. Enter maintenance and stop/drain queue workers. Keep them stopped through
   migration and requeueing; preserve the prior release for rollback review.
2. Deploy matching backend, frontend assets and additive migrations, including
   bank `parse_generation`. Keep model/table/job/notification names stable.
3. Run `php artisan stockflow:bank-imports:requeue-active` while workers are
   stopped. Inspect queued/failed counts; resolve any dispatch failures before
   continuing. Each active replacement receives a fresh generation; queued
   legacy jobs without generations cannot alter it. Reviewed data is retained.
4. Restart workers. Run `stockflow:identity:diagnose` and
   `stockflow:assistant:diagnose` and investigate any failures.
5. Smoke-test admin/limited login, inventory conflict/reapply and close,
   bank replacement processing, voucher stale-action feedback and public shift
   links. Leave maintenance only after readiness checks pass.

Read-only historical inspection: `php artisan stockflow:integrity:diagnose`.
It emits JSON lines with issue and session IDs for cancelled inventories with
posted movements and partially submitted recipe sessions; a nonzero exit means
findings exist. Preserve output for review. It never repairs records. Historical
repair requires a separately reviewed operation, not blanket recalculation.

Do not roll old workers back onto new active imports without a reviewed recovery
procedure. A database restore or historical rewrite is not part of this runbook.

Noticeboard obsolete image cleanup runs after the outer database/audit commit.
A cleanup failure is reported but does not roll back the committed action or
make the assistant replay it. The result can be an unreferenced private file;
inspect storage separately instead of restoring a database reference to a
missing image. Historical file cleanup is not automatic repair.
