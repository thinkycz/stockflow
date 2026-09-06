# Bank-independent CZK statement imports

Implemented BETA badges for bank statements and the assistant; removed the bank allowlist and bank-specific upload copy. CZK and the 10 MB PDF limit remain. Existing nullable database columns/getters/frontend fields already support omitted metadata; parser validation and persistence now preserve null bank codes, statement numbers, credit/debit totals and counts. Transaction sums/counts are calculated separately for integrity checks and never substituted for missing printed summaries.

Duplicate detection compares normalized, decrypted IBANs (or domestic account numbers with bank codes) within the same company, store and covered period. It runs under the existing store row lock. Unknown account identities do not cause speculative duplicate matches. The obsolete bank/statement-number unique index is removed by a migration; file-hash uniqueness remains. Old unsupported-bank warnings are ignored, while balance/currency warnings remain blocking.

## Live verification, 2026-09-06

The user-supplied six-page August PDF was sent directly through `BankStatementParser`, without persisting an application statement. The original and extracted private payloads were not added to the repository.

- MiniMax M3 free accepted the PDF with the explicit `cloudflare-ai` parser. The initial response failed the app contract: it returned alternative field names, despite the JSON-schema request. Therefore lack of native file input was not the reproduced cause.
- The prompt now embeds the same schema used by the SDK, describes stacked payment-symbol headers, and requests up to 24,000 output tokens. Later requests produced the required 47 transactions with zero integrity warnings.
- All 47 ordered booking dates and signed amounts were independently compared with local PDF text extraction: exact match. Printed totals: opening 99,047.59, credits 194,835.44, debits 77,240.00, closing 216,643.03 CZK. Counts: 44 credits and 3 debits.
- Categories: 31 card settlements, 6 Wolt, 4 Bolt, 3 other incoming, 3 outgoing. The description spanning pages was retained. MiniMax eventually extracted all 31 card-specific YYYYMMDD symbols correctly but left their sales-date fields empty; PHP now derives those dates deterministically from valid symbols, with regression coverage. Manual draft editing remains unchanged.
- The two-page synthetic different-bank text PDF passed with two transactions and no integrity warnings. Missing source totals/counts remained null; the closing balance was 850.00 CZK.
- The image-only version of that fixture returned unusable extraction through the free MiniMax/Cloudflare path. Scans are accepted for attempted extraction, but reliable OCR is **not verified**. Invalid extraction shows guidance to use a clearer scan or a bank-generated text PDF. No paid OCR fallback was added.
- Gemma 4 31B free returned provider rate-limit errors on the supplied PDF and the scan comparison. No accuracy or reliability comparison could be completed for Gemma. MiniMax remains the importer and assistant model.

The provider's free document parsing behavior is documented at https://openrouter.ai/docs/guides/overview/multimodal/pdfs . Free endpoints can be unavailable or rate-limited; successful tests do not guarantee future extraction accuracy.

## Validation

- Parser request tests cover real SDK attachment serialization, free parser selection, output allowance and explicit schema with both text and image-only synthetic fixtures. These HTTP-faked tests do not assert OCR quality.
- Bank parsing/integrity tests cover optional metadata, currency blocking, account-aware duplicates, legacy warnings, malformed fields, deterministic card dates, rate limits, draft preservation, generation fencing and timeouts.
- Architecture, existing bank authorization/reconciliation/controller/maintenance tests and locale parity pass.
- PHPStan at maximum level passes (`--debug`, 1 GB memory; sandbox disallows the normal parallel worker socket).
- Frontend type-check and build pass. Browser checks cover upload plus desktop/mobile badges and bank-neutral copy in all three locales using an isolated SQLite database.
- The local MySQL database had not run the original bank-statement migrations. Applied only the two bank prerequisites and the new index-removal migration. No user statement was imported and unrelated pending migrations were left alone.
- Pre-commit verification: `make fix` followed by `make check` passed in an isolated checkout, including dependency audits, frontend checks, production cache smoke, 1,111 PHP tests (19 skipped), and all 72 browser tests. Formatter-only changes to unrelated baseline files were excluded from the feature commit. Concurrent workspace changes were not included.
