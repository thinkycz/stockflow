# September 4 remediation and refactor

Approved scope: all twelve findings in `docs/audits/2026-09-04-codebase-audit.md`, domain organization throughout backend/frontend, preserved framework entrypoints, coordinated deployment, explicit inventory conflict resolution. No production deployment or historical repair.

## Current state

Baseline: 17581dc, inherited untracked audit report. Baseline Vitest has one obsolete voucher-tabs assertion; PHPStan/build/PHP/MySQL/browser checks passed in audit. No application changes inherited.

## Work slices

- [x] F01/F03 inventory lifecycle and revision/conflict contract (inventory worker).
- [x] F05/F06/F09 digest retention, parser generations, batched reconciliation (async worker).
- [x] Frontend feature organization, F07 action feedback, F11 gate (frontend worker).
- [x] F02 identity and F04 recipe boundaries (lead).
- [x] F08/F10 store direction, bounded history and aggregates (lead).
- [x] Backend domain migration, service separation and boundaries (lead after behavioral slices).
- [x] Assistant atomic outcome, diagnostics, shared commissions, F12 docs.
- [x] Integrated checks, MySQL races, browser tests and independent gap review.

## Verification

Implementation and independent review are complete. The canonical gate passed on September 5; evidence and limitations are recorded below.

## Implementation and regression map

| Finding | Implementation                                                                | Regression evidence                                                                                               |
| ------- | ----------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| F01     | Inventory session lock and terminal-state cancellation guard                  | InventorySessionServiceTest, MySqlPeriodMutationConcurrencyTest close/cancel races                                |
| F02     | Identity AccountLifecycleService before guard logout                          | MeDestroyControllerTest real bearer/cookie preservation and limited deletion                                      |
| F03     | InventoryDraftRowInput, server revision CAS, typed conflict; feature autosave | InventoryDraftRowInputTest, InventoryDraftRowControllerTest, inventory-autosave.test.ts, browser conflict/reapply |
| F04     | Standalone-only legacy recipe lookup/submission, atomic parent guard          | RecipeTestControllerTest, RecipeTestSessionServiceTest direct bypass/corrupt child                                |
| F05     | OperationalDigestRetentionService shared boundary and bounded dates query     | DailyOperationalDigestServiceTest 120-day retention                                                               |
| F06     | Bank parse_generation, generation-fenced claims/callbacks/maintenance         | ParseBankStatementJobTest, MaintainBankStatementImportsJobTest                                                    |
| F07     | Shared action-error wrapper for voucher stale mutations                       | feature-workflows.test.ts and voucher controller tests                                                            |
| F08     | Outgoing transfers scoped to displayed source store                           | StoreShowControllerTest direction and gross reversal fixtures                                                     |
| F09     | Parent/statement days loaded once for reconciliation                          | BankStatementReconciliationServiceTest 10/100 transactions, two queries                                           |
| F10     | 50-row stable history plus independent SQL totals                             | StoreShowControllerTest 55/58 movements, fractional aggregates and pagination                                     |
| F11     | Voucher destination behavior retained, obsolete tabs assertion removed        | ui-consistency-contract.test.ts, navigation/browser voucher coverage                                              |
| F12     | Architecture/guidelines/ADRs, read-only diagnostics and deployment runbook    | DomainArchitectureTest, AuditIntegrityCommandTest                                                                 |

All first-party business service classes migrated from flat Services/Operations
locations; frontend workflows now have feature owners. Finance/payroll read
assembly and inventory projections are separated from mutations. Administration
is split into resource-specific operations. Commission decimal definitions are
shared with workflow-specific rounding preserved.

Independent review identified and prompted additional corrections: preserve
committed assistant success after afterCommit exceptions; include external
success in recovery; block uncertain/running external replay; domain actor
checks in bank/noticeboard/checklist operations; successful assistant permanent
noticeboard deletion branch; defer obsolete image cleanup until outer commit.
Typed inventory input now replaces the cross-layer mutable array contract.

Intermediate evidence: 64 architecture tests before the core-boundary addition,
111 Vitest tests, PHPStan max; initial isolated MySQL run 83 tests / 372 assertions.
The first PHP run overlapped an asset rebuild and hit Inertia version conflicts.
The canonical gate then reached 1058 PHP passes with one new numeric JSON test
assertion mismatch; its assertion now accepts equivalent integer/float encoding.
Final gate evidence and browser completion remain pending. No production actions.

## Final verification — September 5

- `make fix` followed by `make check`: exit 0. PHPStan max, Prettier/Pint,
  Composer validation/platform/audit, production dependency npm audit, TypeScript,
  production build, cache build/clear, Vitest, Pest and Playwright passed.
- Canonical gate: 111 Vitest tests, 1097 PHP tests / 55589 assertions,
  69 Playwright tests. Including the later 1000-movement payload regression,
  the final full PHP rerun passed 1098 tests / 55597 assertions (19 expected
  skips). The 10-test store suite also passed separately.
- Expanded MySQL 8.4 suite: 118 tests / 512 assertions, including real
  two-connection inventory races, bank generation/recovery/reconciliation and
  store aggregate behavior. Disposable container stopped after verification.
- Independent review found no remaining issue in its bounded re-review of the
  corrected findings. Additional direct-domain and fault-injection regressions
  passed before the final gate.
- No obsolete flat service/operation implementations remain. Model, route, page,
  job and notification identifiers remain stable. No environment files changed.

Limits: SQLite skips 17 MySQL lock checks and two explicitly opt-in live provider
smokes; lock coverage ran separately on MySQL. External services are mocked;
no paid AI calls, production access, deployment or historical repair occurred.
Browser evidence is the isolated repository Playwright suite, including admin,
limited-user, public links and mobile layouts. Production worker/cron behavior
still requires the documented maintenance deployment and readiness checks.

The earlier MySQL expansion revealed a test counting all bank rows after tests
that intentionally commit fixtures. Its no-duplicate assertion is now scoped to
the tested store; the expanded suite passes. Test-generated voucher print output
was restored to its prior tracked content. All implementation changes remain
uncommitted for review.
