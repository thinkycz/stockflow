# Bank Statement AI Import Verification

## Claim

Administrators can import, review, confirm, and reconcile Czech Savings Bank statements without exposing unmasked account data, storing plaintext originals, or changing daily statements.

## Evidence

- Targeted backend coverage passes for private encrypted upload/download, hash and logical deduplication, queue idempotency, safe provider failure, editable lifecycle, integrity blocking, all reconciliation formulas, exact CZK 5.00 tolerance, cross-month periods, missing days, and live Statements-page refresh.
- `npm run test:unit`: 21 files and 83 tests passed.
- `npm run type-check`: passed.
- `npm run build`: passed.
- `npm run e2e -- tests/e2e/bank-statements.spec.ts`: Chromium upload/detail flow passed.
- `make check`: passed, including PHPStan at max, Prettier, Pint, Composer validation/platform/audit, npm audit, production optimization smoke, and the full Pest suite with 908 passed, 2 opt-in tests skipped, and 52,642 assertions.
- npm audit required updating the aligned Tiptap packages from 3.29 to 3.31; the resulting audit reports zero vulnerabilities.
- The OpenRouter PDF smoke test exists and uses an anonymized synthetic PDF plus the explicit `cloudflare-ai` parser, but remains intentionally opt-in through `OPENROUTER_SMOKE_TEST`; it was not executed against a live provider in this verification.
- No `.env*` file changed, and the supplied real bank statement was not added to the repository.

## Verdict

Verified for release, with live OpenRouter connectivity remaining an environment-level opt-in smoke check rather than a code blocker.
