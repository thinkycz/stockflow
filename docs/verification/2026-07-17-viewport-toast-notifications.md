# Viewport Toast Notifications Verification

## Claim

Global success and error flashes are presented as accessible viewport toasts;
successes auto-dismiss and errors persist.

## Evidence

- `npm run type-check`: passed
- `npm run build`: passed
- `npm run test:unit`: 4 tests passed
- Focused Playwright suite: 4 tests passed, covering fixed viewport placement,
  repeated identical success flashes, manual and timed dismissal, persistent
  errors, and success/error ARIA roles
- Targeted Prettier check: passed
- `git diff --check`: passed
- `make check`: blocked during PHPStan by 27 existing `brick/math` type errors
  under `packages/thinkycz/laravel-core`
- `make lint`: Prettier passed; Pint found an existing `mb_str_functions`
  issue in `app/Http/Controllers/Web/Item/ItemSearchController.php`
- `make test`: 447 passed and 2 unrelated statement tests failed because they
  expect 30 days while July 2026 has 31

## Verdict

The viewport toast implementation is verified. The full repository gate remains
blocked by unrelated core-package, formatting, and date-sensitive test failures.
