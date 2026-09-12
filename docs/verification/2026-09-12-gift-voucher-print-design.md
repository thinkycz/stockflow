# Teacha printed gift vouchers

Implemented the cream/green (#344C28) print design, outlined Teacha logo, customer-facing redemption instructions, active retail branch addresses, and a 45 × 15 mm stamp/signature area. Existing batch messages and expiration dates remain in use. Branches are scoped to the batch owner and sorted by name; warehouses and inactive stores are excluded. A missing address leaves the branch name visible.

The print logo is derived from `resources/images/teacha-logo.svg`, with the existing Poppins Bold lettering converted to paths and the surrounding empty canvas cropped. Rendering does not require a font download. Printing waits for images and document fonts.

## Verification

- GiftVoucherPrintControllerTest: 2 tests, 12 assertions passed, including branch ownership, address output, warehouse/inactive exclusion and 3-up pagination.
- PHPStan at the configured maximum level: changed controller passed (`--debug` used to avoid sandbox TCP worker restrictions).
- Frontend type-check and Vite build passed; changed Vue/translations formatted with Prettier and PHP with Pint.
- Isolated Chromium loaded the built Inertia print page with synthetic props (no database writes). Verified Teacha overrides a supplied alternate logo and printing waits for readiness.
- PDF checks covered 1, 3, 4 vouchers; a 240-character message with 999,999.99 CZK and expiration; Czech, Slovak and English; and backgrounds disabled. Page counts were 1, 1, 2, 1, 1, 1, 1 respectively. A4 dimensions and all 14 QR codes were verified with PyMuPDF and ZXing. Representative rendered pages were inspected for clipping, overlap, logo appearance, diacritics and stamp space.
- Sample: `output/pdf/teacha-gift-vouchers-design-preview.pdf`. The sample intentionally contains clearly labelled illustrative branch addresses and non-issued QR codes. Live printing uses addresses recorded for the owner's active retail stores.
- Existing modified `output/pdf/gift-vouchers-print-4.pdf` and `output/playwright/gift-vouchers-print-4.png` were left untouched.

Pre-commit verification: `make fix` and the complete `make check` passed, including PHPStan, formatting, dependency audits, frontend build/type-check, unit tests, deployment smoke checks, 1,263 PHP tests (19 skipped; 57,074 assertions), and all 86 browser E2E tests. The initial sandboxed check could not open a PHPStan worker port; rerunning with the required permissions passed. Existing modified print artifacts were backed up before E2E and restored afterward.

Follow-up: removed the repeated brand name beside the logo and reduced the logo box from 18 to 15 mm. Rebuilt and repeated all seven PDF scenarios; page counts and all 14 QR codes passed. The three-up sample was visually inspected and refreshed.

Spacing follow-up: increased the copy header top inset from 5 to 8 mm to give the logo clearance from the frame. Build, all seven PDF scenarios and QR checks passed; refreshed the visually inspected sample.

Value spacing follow-up: increased the gap above the amount from 2 to 4 mm. Build, all seven PDF scenarios and all 14 QR codes passed; normal and long-message renders inspected and sample refreshed.
