# Store-Specific Slack Notifications Plan

## Phase 1: Store configuration

- Add failing persistence and validation tests.
- Add the nullable store column, getter, validity rule, controller mappings, Vue fields, and translations.
- Verify the focused store tests and frontend type-check.

## Phase 2: Notification infrastructure

- Install the Laravel Slack channel package.
- Add typed bot-token configuration.
- Add the operational activity enum/event, post-commit listener, queued Block Kit notification, and Czech translations.
- Test routing, disabled configuration, payload formatting, and failure isolation.

## Phase 3: Domain integration

- Emit committed activities from attendance and corrections.
- Emit inventory save/close without reconciliation duplication.
- Emit statement save/clear/restore without snapshot noise.
- Emit manual movements and reversals, including two-sided transfer routing and channel deduplication.
- Add focused regression tests for included and excluded behavior.

## Phase 4: Verification

- Run formatting, targeted tests, frontend type-check/build, and `make check`.
- Review implementation against every requirement and record evidence under `docs/verification/`.
