# Store-Specific Slack Notifications Verification

## Verdict

Ready for deployment configuration. The implementation, automated routing behavior, payload shape, frontend forms, static analysis, build, and repository test suites are verified. Live Slack delivery remains an operator smoke test because no production Slack credentials were provided.

## Evidence

- `make fix` — passed; Prettier and Pint formatted the worktree.
- Focused PHP suite — 82 tests passed with 265 assertions before final edge-case additions.
- Focused edge-case suite — 21 tests passed with 61 assertions.
- `npm run type-check` — passed.
- `npm run test:unit` — 14 tests passed across 6 files.
- `make check` — passed after a clean rerun:
    - PHPStan level max: no errors across 310 files.
    - Prettier and Pint checks: passed.
    - Composer audit and npm audit: no vulnerabilities.
    - Composer platform requirements and strict manifest validation: passed.
    - Vue TypeScript check and production Vite build: passed.
    - Pest: 528 tests passed with 10,663 assertions after subsequent inventory access regression coverage.
- `git diff --check` — passed.

## Behavior covered

- Nullable, trimmed, non-unique store channel persistence, validation, create/edit/detail props, frontend typing, rendering, and translations.
- Czech Block Kit content, Europe/Prague timestamps, scalar facts, escaped text, authenticated links, queued delivery, and post-commit dispatch.
- Attendance transitions and corrections; finalized inventory; statement save/clear/restore; manual stock movement create/reverse.
- Source/destination transfer perspectives, shared-channel deduplication, missing token/channel silence, rejected actions, rollback silence, enqueue failure isolation, and inventory reconciliation suppression.

## Deployment handoff

1. Run the database migration.
2. Set `SLACK_BOT_USER_OAUTH_TOKEN` and restart long-running workers after configuration changes.
3. Enter each store's Slack channel name or ID in store administration.
4. Invite the Slack bot to configured private channels and confirm queue workers are running.
5. Perform one live notification smoke test per representative activity and one transfer between two stores.

## Known external gap

No credentialed Slack API call was made. This is an operational dependency, not an automated-test failure.
