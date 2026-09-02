# Full audit remediation tracker

All six implementation phases and the canonical local gate are complete.
Deployment identity and worker health are deliberately rechecked against the
real production state after deployment.

| Finding / contract                                                                         | Phase | State    | Regression evidence                                                    |
| ------------------------------------------------------------------------------------------ | ----: | -------- | ---------------------------------------------------------------------- |
| Cookie API mutations require a matching readable XSRF token                                |     1 | verified | `EnsureApiCookieCsrfTest`, `CsrfCookieShowControllerTest`              |
| Bearer API mutations remain CSRF-exempt; stale cookies do not block login                  |     1 | verified | `EnsureApiCookieCsrfTest`                                              |
| Optional CORS origins never produce null/empty entries                                     |     1 | verified | `CorsConfigTest`, `CorsPreflightTest`                                  |
| Forgot-password responses do not enumerate users or mutate credentials                     |     1 | verified | API/web forgot-password controller tests                               |
| Failed reset-link delivery removes only the failed token                                   |     1 | verified | `ForgotPasswordControllerTest`                                         |
| Successful broker reset consumes the token and revokes database tokens                     |     1 | verified | API/web reset-password controller tests; MySQL token race              |
| Non-local deployment is seed-free and demo seeding fails closed                            |     1 | verified | `MakefileDeploymentTest`, `UserSeederTest`                             |
| Required recipe catalog replacement is migration-owned                                     |     1 | verified | deployment architecture assertion and fresh MySQL migration            |
| Administrator bootstrap supports create, no-op, rotate, and invalid-state rejection        |     1 | verified | `AdminBootstrapCommandTest`                                            |
| Production rejects missing/default identity and invalid warehouse state                    |     1 | verified | `IdentityReadinessCommandTest`; production Makefile gate               |
| New deployment output is localized in English, Czech, and Slovak                           |     1 | verified | translated command assertions and `I18nParityTest`                     |
| Workers delete when pristine, archive with history, block on live work, and restore        |     2 | verified | worker destroy/restore tests; lifecycle FK-family matrix               |
| Archived workers remain in history but leave prospective selectors/actions                 |     2 | verified | `ArchivedWorkerProspectiveMutationTest` and controller/service tests   |
| Assistant worker removal/restoration uses the same typed lifecycle                         |     2 | verified | native tool architecture and application mutation tests                |
| Warehouse removal is forbidden; live store work blocks removal                             |     2 | verified | store destroy tests and store lifecycle service matrix                 |
| Pristine stores delete; historical stores become inactive without losing records           |     2 | verified | store destroy tests and every store FK-family lifecycle case           |
| Inactive store history stays readable while every prospective mutation fails closed        |     2 | verified | inactive-store controller/service contracts and MySQL races            |
| Inactive-store public shift capabilities expose no calendar, requests, toggle, or manifest |     2 | verified | `ShiftShareLinkDestroyControllerTest`                                  |
| Store/worker controllers and assistant responses expose deleted/archived/blocked outcomes  |     2 | verified | destroy controller tests and native action tests                       |
| Limited item preload/search exposes only assigned-store availability                       |     2 | verified | item search and stock movement create controller tests                 |
| Gift-voucher branding uses multipart POST plus method override                             |     3 | verified | gift-voucher feature tests and both Playwright voucher flows           |
| Bank save/confirm/reopen/retry errors surface globally and per transaction row             |     3 | verified | `bank-statements-ui-contract.test.ts` and bank feature tests           |
| Backend/frontend sentence keys and file-based dot keys resolve in all locales              |     3 | verified | token-based `I18nParityTest` and frontend i18n tests                   |
| Payroll uses exact two-decimal `BigDecimal` arithmetic in all five mutation paths          |     4 | verified | `MoneyTest`, adversarial payroll service/controller tests              |
| Exact zero and empty-payslip overrides pass; a negative cent fails                         |     4 | verified | payroll exact-zero and no-activity worker regressions                  |
| Financial/payroll period mutations lock and recheck in a consistent order                  |     4 | verified | service tests and real-service MySQL close races                       |
| Bank parse/edit/confirm/reopen/retry/failure transitions lock their parent row             |     4 | verified | bank service/job tests and real-service MySQL confirm races            |
| Upload/retry dispatch failures preserve one retryable failed statement                     |     5 | verified | bank controller and maintenance job tests                              |
| Parser timeout/exhaustion transitions active rows to safe failures                         |     5 | verified | `ParseBankStatementJobTest`, `MaintainBankStatementImportsJobTest`     |
| Maintenance reclaims stale queued rows and times out stale processing rows                 |     5 | verified | `MaintainBankStatementImportsJobTest`; schedule inspection             |
| Assistant queue has dedicated worker provisioning and a heartbeat release gate             |     5 | verified | supervisor script, diagnosis tests, schedule inspection                |
| Duplicate uploads and races retain uniqueness across every status and retry in place       |     5 | verified | upload reuse, parse duplicate, and retry regressions                   |
| Parser trims whitespace but rejects excess money precision as invalid payload              |     5 | verified | bank integrity and parser job tests                                    |
| Period-less active imports do not leak into monthly reports                                |     5 | verified | bank reconciliation month-scope test                                   |
| `make check` owns the isolated, non-reused Playwright suite                                |     6 | verified | Makefile, Playwright config, architecture assertions                   |
| Shared GitLab include is immutable and MySQL invariants are required                       |     6 | verified | pinned commit `96725394d4ca7f4568bef07e088b312def6a6a39`; CI MySQL job |
| Production builds caches and runs identity/assistant diagnostics before `up`               |     6 | verified | `deploy-smoke`, production dry-run, diagnostic command tests           |

## Blockers

No implementation blockers remain. Live production state cannot be fabricated
locally: first deployment must bootstrap or rotate the real administrator and
must start the dedicated assistant worker before both production diagnostics
can pass.

## Deployment action

Run the two diagnostics against the deployed production database, Redis
instance, and supervisor worker before bringing the application up.
