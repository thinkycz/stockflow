# Gift Vouchers Traceability Matrix

| Req ID | Source           | Requirement                                                                | Phase | Status   | Verification                                            |
| ------ | ---------------- | -------------------------------------------------------------------------- | ----- | -------- | ------------------------------------------------------- |
| GV-01  | approved plan    | Secure unique batch issuance with CZK value and optional expiration        | 1     | verified | Service tests cover batches of 1, 10, 20, and 100       |
| GV-02  | approved plan    | Audited active/redeemed/voided lifecycle and derived expiration            | 1     | verified | Lifecycle, expiration, void, and reversal service tests |
| GV-03  | approved plan    | Two-step ticketed redemption with hard store and concurrency enforcement   | 2     | verified | Controller and service feature tests                    |
| GV-04  | approved plan    | Admin branding, overview, exact search, issue, reprint, void, and reversal | 2–3   | verified | Feature tests and admin Playwright flow                 |
| GV-05  | approved plan    | Limited-user redemption on assigned retail store                           | 2–3   | verified | Feature tests and limited-user Playwright flow          |
| GV-06  | approved plan    | Three visually polished vouchers per portrait A4 sheet                     | 2–3   | verified | Playwright for 1/3/4/10/20; rendered two-page A4 PDF    |
| GV-07  | repository rules | Three-locale translations, strict types, PHPStan max, full check gate      | 3–4   | verified | `make check`: 684 PHP and 46 frontend tests             |
