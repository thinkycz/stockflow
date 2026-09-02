# Full audit remediation verification

Status: locally verified and ready for deployment-state gates.

## Canonical local gate

The final `make fix` followed by `make check` completed successfully:

- PHPStan level max: no errors; Prettier and Pint: passed.
- Composer audit: no advisories; npm production audit: zero vulnerabilities;
  Composer platform and strict manifest validation: passed.
- Vue type-check and Vite production build: passed.
- Vitest: 22 files and 93 tests passed.
- Laravel production optimize/clear smoke: passed.
- Pest: 1,029 passed with 54,164 assertions. The 17 expected local skips are
  the 15 MySQL-only tests run separately below and two credentialed live
  OpenRouter smoke tests.
- Playwright: all 67 Chromium flows passed on the isolated test server.

## Targeted evidence

- Authentication: cookie CSRF, bearer exemption, stale-cookie login, random
  token issuance, CORS preflight, generic password recovery, failed-mail token
  cleanup, one-time reset, and post-reset token revocation all pass focused
  feature tests.
- Lifecycle/privacy: every worker/store FK family, prospective archived/inactive
  mutation, historical read-only page, typed assistant outcome, public shift
  token, and limited item projection is covered by a regression.
- Money/concurrency: exact-cent tests cover all five payroll paths, including
  `0.01 + 0.06 - 0.07`, exact zero, no-activity overrides, and `-0.01`.
- Bank imports: dispatch failure, job exhaustion, stale recovery, same-row
  retry, duplicate handling, strict parser validation, nested UI errors, and
  month isolation pass focused tests.
- Localization: frontend, backend, core notification, core HTTP status, and
  password-broker dictionaries have parity; every static backend `__()` call
  resolves in English, Czech, and Slovak. New deployment command messages and
  prompts are included.

## MySQL evidence

A disposable `mysql:8.4` database was migrated from empty and ran the required
row-lock suite. Result: 15 passed, 45 assertions. The race harness invokes the
real `FinancialReportService::close`, `PayrollReportService::close`, and
`BankStatementService::confirm` methods, observes their target `FOR UPDATE`,
then releases competing mutations. Store deactivation and password reset token
consumption races also passed. The disposable container was removed afterward.

## Release structure evidence

- `GET /api/v1/csrf-cookie` appears in `route:list`.
- `schedule:list` contains five-minute bank import maintenance and one-minute
  assistant queue heartbeat jobs.
- `make -n production` contains migration, identity diagnosis, assistant
  diagnosis, queue restart, and application `up`; it contains no `db:seed`.
- GitLab CI uses immutable include commit
  `96725394d4ca7f4568bef07e088b312def6a6a39` and a MySQL 8.4 invariant job.
- `git diff --check` passes and `.env*` has no tracked change.

## Deployment-only gates

These checks require the real production state and therefore remain operator
release steps, not local implementation blockers:

1. On a new install, run `php artisan stockflow:admin:bootstrap <email>` and
   answer both hidden prompts. On an existing default-credential install, run
   the same command with `--rotate`.
2. Start the dedicated `assistant` queue supervisor and wait for the scheduled
   heartbeat.
3. Require both `php artisan stockflow:identity:diagnose` and
   `php artisan stockflow:assistant:diagnose` to succeed before bringing the
   application up.
