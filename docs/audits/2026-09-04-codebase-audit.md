# StockFlow codebase audit — 2026-09-04

Audited revision: `17581dc`. Outcome: **targeted remediation required**. Application code was not changed.

## Results and evidence

Twelve actionable findings: three P1 correctness/data-integrity issues, eight P2 issues, and one P3 documentation issue. P1 means fix before relying on the affected workflow; P2 means schedule a targeted correction; P3 means maintenance work. No unauthenticated takeover or cross-company disclosure is claimed.

- **PHPStan level max:** Passed
- **Prettier / Pint checks:** Passed
- **Vue TypeScript / production build:** Passed
- **Vitest:** **1 failed, 92 passed** across 22 files
- **Pest, isolated SQLite memory database:** 1,030 passed; 17 skipped; 54,192 assertions
- **CI-listed targeted MySQL suite, disposable MySQL 8.4:** 79 passed; 355 assertions
- **Playwright, isolated SQLite database and port 8017:** 68 Chromium tests passed
- **Composer advisory audit, platform requirements, strict manifest validation:** Passed; no advisories reported
- **npm production dependency audit:** Zero vulnerabilities reported

The Vitest failure is `UI consistency contract > shared tabs own requested tab families`, at `tests/Unit/ui-consistency-contract.test.ts:53`. The voucher overview is now a separate page and no longer contains `<Tabs`. Therefore the canonical `make check` is currently blocked despite the other passing checks.

Verification used existing tests plus a temporary PHP reproduction harness outside the repository. Its fresh results were:

```text
cancel closed inventory: closed -> cancelled; stock=5; ledger_rows=1
old parser attempt fails new attempt: attempt=2; status=failed
legacy submits session child: parent_submitted=null; child_submitted=true
API delete main admin: HTTP 204; admins_remaining=0; stores_remaining=0
digest recreates pruned day: first=2026-05-27; after_prune=2026-05-27
bank reconciliation: 10 transactions -> 31 queries; 100 -> 301 queries
second editor, same version: requested=9; persisted=5; returned_version=1
```

## Prioritized findings

### F01 — P1: Cancelling a closed inventory corrupts its lifecycle

**Evidence:** [InventorySessionService.php:235](/Users/longdo/Herd/stockflow/app/Services/InventorySessionService.php:235), [ManageInventory.php:150](/Users/longdo/Herd/stockflow/app/Operations/Inventory/ManageInventory.php:150). Confidence: high; reproduced through the operation used by the controller.

`cancelDraft()` unconditionally updates the session to `cancelled`. The operation's `draft()` lookup filters ownership and ID, but not status. Calling cancellation after close therefore succeeds. The reproduction retained stock of 5 and one reconciliation movement while changing the session from closed to cancelled. Closed-session history and consumption calculations can then omit the count even though its stock effect remains.

**Correction:** perform cancellation in a transaction, lock the session, and require `draft` status after locking. Reject cancellation of closed sessions; repeated cancellation may return an idempotent success. Recheck relevant authorization under the lock. Do not undo posted stock through this endpoint.

**Acceptance:** close then cancel is rejected with no changes; a MySQL close/cancel race produces either a cancelled unposted draft or a closed posted session, never the mixed state.

### F02 — P1: Compatibility API can delete the sole company administrator

**Evidence:** [MeDestroyController.php:38](/Users/longdo/Herd/stockflow/app/Http/Controllers/Api/Me/MeDestroyController.php:38), `AdministrationManagementService::deleteUser`, ADR 0002. Confidence: high; reproduced through the HTTP kernel.

The API authenticates a user and calls `$user->delete()` directly. Unlike the web administration service, it does not reject deletion of the main administrator. With one administrator and a store, `POST /api/v1/me/destroy` returned 204 and left zero administrators and zero stores. Other historical foreign keys can instead make deletion fail; they are not a reliable policy guard. This contradicts the single-company identity invariant and bypasses the normal lifecycle checks.

**Correction:** reject administrator self-deletion before logout/deletion, preserving the compatibility endpoint for permitted limited-account deletion. Keep company teardown outside this endpoint. Use the same explicit invariant in all deletion paths.

**Acceptance:** cookie and bearer admin requests are rejected and preserve administrator, tokens, stores, and history; authorized limited self-deletion and unauthenticated rejection retain their documented behavior. Replace the generic self-delete test's assumption with role-specific cases.

### F03 — P1: Inventory autosave acknowledges discarded edits

**Evidence:** [InventorySessionService.php:154](/Users/longdo/Herd/stockflow/app/Services/InventorySessionService.php:154), [InventoryDraftRowController.php:31](/Users/longdo/Herd/stockflow/app/Http/Controllers/Web/InventoryCount/InventoryDraftRowController.php:31), [Index.vue:228](/Users/longdo/Herd/stockflow/resources/js/pages/inventory-counts/Index.vue:228). Confidence: high; equal-version collision reproduced.

Versions are local counters initialized from the loaded draft. Two editors can both send version 1. The second request returns the existing row without saving, the controller emits `saved: true`, and the client marks the edit saved without inspecting the response. In the reproduction, the second editor requested 9 but the database retained 5. Within one editor, every completion also changes the same save state: an older success can mask a newer failure.

**Correction:** use a server row revision with compare-and-swap semantics; return a conflict and authoritative row on mismatch. Serialize/coalesce requests per row and associate completion state with the submitted revision. Closing must wait for the latest acknowledged value of every edited row. Include the assistant caller in the changed save contract.

**Acceptance:** two editors cannot silently overwrite or falsely acknowledge each other; reversed response order and newest-request failure remain visibly unsaved; retry/reload resolves conflicts explicitly; close cannot post stale values after a failed save.

### F04 — P2: Legacy recipe endpoints bypass atomic session submission

**Evidence:** [RecipeTestController.php:60](/Users/longdo/Herd/stockflow/app/Http/Controllers/Web/Recipe/RecipeTestController.php:60), [RecipeTestService.php:107](/Users/longdo/Herd/stockflow/app/Services/RecipeTestService.php:107), [RecipeTestSessionService.php:81](/Users/longdo/Herd/stockflow/app/Services/RecipeTestSessionService.php:81). Confidence: high; child submission reproduced.

The legacy GET/PUT ownership check accepts attempts belonging to new three-recipe sessions. The legacy service submits an individual child, and its response exposes `correct_steps`, before the parent is submitted. The parent service subsequently processes all children without rejecting this prior submission. This violates ADR 0006's atomic submission boundary and allows answer feedback before the complete test is final. The reproduction left a submitted child under an unsubmitted parent.

**Correction:** restrict legacy show/submit to attempts with no session ID, enforced in both controller lookup and service. Preserve genuine legacy attempts. Reject unexpected pre-submitted children during parent submission and inspect existing partial sessions before deciding whether any data repair is needed.

**Acceptance:** session-child GET/PUT cannot reveal or submit answers through legacy routes; standalone legacy attempts still work; complete three-answer submission remains atomic and one-time.

### F05 — P2: Digest retention makes generation recreate expired dates

**Evidence:** [DailyOperationalDigestService.php:32](/Users/longdo/Herd/stockflow/app/Services/DailyOperationalDigestService.php:32), [PruneOperationalDigestHistoryJob.php:18](/Users/longdo/Herd/stockflow/app/Jobs/PruneOperationalDigestHistoryJob.php:18). Confidence: high; prune/recreate cycle reproduced without sending notifications.

Generation searches from the original activation date every time. Retention deletes digests older than 90 days without advancing that lower bound. Generation then treats pruned dates as missing and rebuilds them, after their activity records have also been deleted. Once the expired backlog exceeds the daily generation capacity, current digests can be starved by this repeating work.

**Correction:** share the retention boundary and start generation at the later of activation date or retention cutoff. Fetch existing dates within the bounded window in one query rather than one existence query per day. Historical regeneration beyond retention remains outside this workflow.

**Acceptance:** simulate more than 120 days of operation, prune, and run generation repeatedly; no expired digest is recreated and yesterday's missing digest remains reachable. Test the cutoff day and Prague date boundary.

### F06 — P2: Parser completion/failure is not tied to an attempt

**Evidence:** [BankStatementService.php:159](/Users/longdo/Herd/stockflow/app/Services/BankStatementService.php:159), [BankStatementService.php:389](/Users/longdo/Herd/stockflow/app/Services/BankStatementService.php:389), [MaintainBankStatementImportsJob.php:57](/Users/longdo/Herd/stockflow/app/Jobs/MaintainBankStatementImportsJob.php:57), [ParseBankStatementJob.php:93](/Users/longdo/Herd/stockflow/app/Jobs/ParseBankStatementJob.php:93). Confidence: high for missing attempt fencing; a stale failure against a new attempt was reproduced. Production race frequency was not measured.

Callbacks identify only the statement and accept any queued/processing state. Maintenance selects stale IDs before invoking `fail()`, which does not recheck the selected attempt or its age. A delayed callback or maintenance overlap can therefore mark a newer retry failed. An old parser result similarly lacks an expected-attempt check before replacing data.

**Correction:** persist an attempt generation when queueing; carry it in the job and require it for claim, completion, and failure. Maintenance must compare the selected generation and stale timestamp in its update. Duplicate or stale jobs become no-ops.

**Acceptance:** pause attempt A, fail/retry as B, then release A's result/failure; B remains unchanged. Cover delayed duplicate jobs, maintenance overlapping retry, and normal timeout recovery.

### F07 — P2: Voucher lifecycle actions again hide validation failures

**Evidence:** [Index.vue:93](/Users/longdo/Herd/stockflow/resources/js/pages/gift-vouchers/Index.vue:93), [Index.vue:107](/Users/longdo/Herd/stockflow/resources/js/pages/gift-vouchers/Index.vue:107), `GiftVoucherService::adminTransition`, `resources/js/lib/action-errors.ts`. Confidence: high from the request/validation/render chain; this exact stale-tab scenario was not browser-reproduced.

Void and reverse-redemption issue raw `router.post()` calls without `withActionErrorToast`, an `onError` handler, or a rendered voucher error. A stale overview can request a transition rejected by the locked server state. The shared server flash conversion requires `X-StockFlow-Action`, which these requests do not send. The user therefore receives no useful explanation. This regresses the September 2 remediation intent.

**Correction:** apply the shared action-error wrapper to both calls, retaining the existing dialog and successful behavior.

**Acceptance:** in a second tab change voucher status, then attempt the obsolete action in the first tab; a localized error is visible, data stays unchanged, and repeated clicks do not create duplicate transitions.

### F08 — P2: Store “transfers out” includes incoming transfers

**Evidence:** [StoreShowController.php:32](/Users/longdo/Herd/stockflow/app/Http/Controllers/Web/Store/StoreShowController.php:32), [StoreShowController.php:139](/Users/longdo/Herd/stockflow/app/Http/Controllers/Web/Store/StoreShowController.php:139). Confidence: high from the query and aggregation.

The movement collection contains both destination and source matches. The outgoing metric filters only `type === TRANSFER`, so a transfer from A to B increments B's “transfers out” count and value too.

**Correction:** also require `source_store_id` to match the displayed store. Preserve the existing gross-history reversal semantics in this focused fix; changing gross metrics into net metrics is a separate product decision.

**Acceptance:** A -> B increments the outgoing metric for A only; B -> A affects B only. Self-unrelated movements remain excluded and history still shows both directions.

### F09 — P2: Bank reconciliation performs three queries per transaction

**Evidence:** [BankStatementReconciliationService.php:32](/Users/longdo/Herd/stockflow/app/Services/BankStatementReconciliationService.php:32), [BankStatementReconciliationService.php:100](/Users/longdo/Herd/stockflow/app/Services/BankStatementReconciliationService.php:100). Confidence: high; measured 31 queries for 10 rows and 301 for 100 rows.

Each eligible transaction resolves its parent, fetches statement IDs for the store, and loads statement days. Repeated sales periods repeat the same reads. This also affects monthly status, which invokes full reconciliation for confirmed imports.

**Correction:** load the parent once and fetch required statement days once per import; index days by date and reuse them across ranges. Keep exact decimal arithmetic, fees, tolerance, missing-day detection, and per-transaction output unchanged.

**Acceptance:** identical reconciliation output for all categories and overlapping ranges; a query-count regression shows bounded query count for 10 versus 100 transactions, including missing-day cases. No cache invalidation subsystem is needed.

### F10 — P2: Store detail loads all movement history and lines

**Evidence:** [StoreShowController.php:40](/Users/longdo/Herd/stockflow/app/Http/Controllers/Web/Store/StoreShowController.php:40), [StoreShowController.php:115](/Users/longdo/Herd/stockflow/app/Http/Controllers/Web/Store/StoreShowController.php:115). Confidence: high for unbounded growth; no production latency measurement.

Every store detail loads all historical movements with their items into an Inertia payload. It separately loads all incoming lines again to aggregate items received in PHP. Page memory, response size, and rendering grow with lifetime history, even when the user needs only recent activity.

**Correction:** paginate movement history at 50 rows with stable date/ID ordering; calculate all-time metrics and received-item summaries with database aggregates independently of pagination. Keep the current inventory projection intact.

**Acceptance:** seed thousands of movements; each response carries at most 50 history entries; all-time totals remain identical across pages; page navigation preserves store context and history details.

### F11 — P2: A stale implementation-shape test blocks the release gate

**Evidence:** [ui-consistency-contract.test.ts:45](/Users/longdo/Herd/stockflow/tests/Unit/ui-consistency-contract.test.ts:45), `GiftVoucherIndexController::__invoke`, `resources/js/pages/gift-vouchers/Index.vue`. Confidence: high; fresh Vitest failure.

The test demands `<Tabs` in the voucher index, but the implemented navigation splits overview, issue, settings, and redemption into pages with legacy-tab redirects. Restoring a tab solely to satisfy this source-string test would contradict the current implementation.

**Correction:** update only the obsolete voucher assertion and add behavioral coverage of the separate destinations and legacy redirects. Keep the shared-tabs checks for pages that still use tabs. Avoid broad test deletion.

**Acceptance:** all 93 existing unit tests plus the relevant updated/new checks pass; voucher navigation and limited-account redemption routing stay covered.

### F12 — P3: Architecture documentation prescribes obsolete validation behavior

**Evidence:** [architecture.md:110](/Users/longdo/Herd/stockflow/docs/architecture.md:110), [bootstrap/app.php:178](/Users/longdo/Herd/stockflow/bootstrap/app.php:178). Confidence: high from local implementation.

The architecture guide describes re-rendering an Inertia component with HTTP 422, but the exception callback redirects with input and errors. The guide also contains older navigation summaries predating configurable limited-user sections, while guidelines still show a magic `forUser()` call despite the explicit-scope convention. These contradictions can cause future changes to reintroduce known bugs or violate architecture tests.

**Correction:** document the actual redirect/error-bag/action-toast path, current section authorization, and explicit static scope calls. Update the affected passages rather than rewriting historical verification reports as though they were current evidence.

**Acceptance:** each documented behavior points to its current enforcement layer and representative test; no contradictory 422 render guidance or magic scope examples remain in authoritative guidance.

## Targeted implementation handoff

1. **Identity and inventory integrity — F01–F03.** Add failing regressions, enforce administrator retention and locked lifecycle transitions, then introduce the revised inventory save response/request contract across browser and assistant callers. Deploy the matching backend/frontend assets together. Audit existing cancelled sessions with reconciliation movements; report candidates for review rather than automatically changing historical stock.
2. **Async lifecycle — F05–F06.** Bound digest recovery by retention and add parser attempt fencing. The parser generation requires a schema change or an explicit reuse of a suitable existing generation field; use a dedicated monotonically increasing generation assigned at queue time. Drain old parser jobs before deployment and requeue active rows under the new generation. Do not let legacy jobs without a generation mutate current rows.
3. **Recipe and user feedback — F04, F07, F11.** Preserve standalone legacy tests while excluding session children, restore action errors, and replace the stale voucher contract. Inspect partial recipe sessions before deciding whether data repair is necessary.
4. **Read correctness and performance — F08–F10.** Fix transfer direction, batch bank reconciliation, and paginate store history with independent totals. Preserve accounting formulas and existing gross-history semantics.
5. **Documentation and verification — F12.** Update affected current docs, run `make fix` then `make check` for any eventual implementation commit, and rerun MySQL races plus the new regressions. The audit itself did not run formatting fixes or commit changes.

Public-interface changes are limited to the inventory save conflict/revision contract and store-history pagination metadata. Internal changes include parser generation and legacy-attempt guards. No new role surface, scheduler, external service, or general caching layer is proposed.

## Optional refactors, separate from confirmed fixes

- Extract the 1,949-line shift page into calendar rendering, request approval, and editor components. Similarly isolate inventory autosave state from its page, beginning with F03. File size alone is not a defect; the benefit is independently testable state transitions.
- Consolidate repeated commission definitions across `StatementService`, `FinancialReportService`, and `BankStatementReconciliationService` after locking their current rounding behavior with fixtures. Do not use this cleanup to change financial policy.
- Gradually replace source-substring tests with behavior/AST checks where syntax is not the intended contract. Retain deliberate architecture rules and required docblocks.
- Treat assistant exactly-once claims cautiously: mutation execution and audit completion are separate writes. The running-state replay guard helps, but crash-between-commit-and-audit reconciliation deserves a separate design/probe before claiming end-to-end exactly-once execution. This is a follow-up hypothesis, not a reproduced duplicate mutation.

## Coverage and limits

The review covered the repository's route/middleware and model-binding structure, core token guard and API boundary, inventory and stock services, statements/payroll/financial report flows, bank parsing/reconciliation, workforce and attendance paths, recipe tests, vouchers, checklists, noticeboard sanitization, assistant approval/job lifecycle, operational digest jobs, Vue interaction patterns, migrations, test configuration, Makefile, and local CI definition. Deep manual tracing focused on lifecycle boundaries and the findings above; this is not a claim that every line or every cross-product of roles/states was exercised.

Existing tests supplied broader coverage for payroll arithmetic, authorization, archive safety, rendering, and localization. SQLite skipped 15 MySQL-dependent checks and two credentialed provider checks; the CI-listed MySQL suite was separately executed successfully. Live provider behavior, real Slack delivery, production datasets, and production query plans were not exercised. The external CI include was not fetched. Advisory results are the local package-manager results at audit time, not a future vulnerability guarantee.

The monolithic canonical check was not reported as passing: Vitest fails, and shared deployment-cache smoke commands were not run. Browser tests generated two tracked print artifacts; those generated changes were restored to the clean starting revision. A disposable MySQL container was removed after verification. No `.env` file or application source was changed.
