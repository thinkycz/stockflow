# Bank Statement AI Import

## Goal

Add an administrator-only, active-store-scoped workflow that archives a Czech Savings Bank PDF statement, extracts its transactions with the existing OpenRouter integration, lets an administrator review the draft, and compares confirmed bank transactions with current daily statements without changing statement data.

## Requirements

- Accept Czech Savings Bank CZK PDF statements up to 10 MB and store encrypted originals on the private disk.
- Process uploads asynchronously on the encrypted `assistant` queue and expose queued, processing, review, confirmed, and failed states.
- Treat all document content as untrusted data. The parser must return structured output and never follow instructions found in the PDF.
- Let administrators add, edit, remove, confirm, reopen, and retry draft imports.
- Validate transaction counts, debit/credit totals, and the opening-to-closing balance equation before confirmation.
- Reconcile card, Wolt, Bolt, and Foodora transactions against live `StatementDay` data with a CZK 5.00 tolerance; other transactions remain visible but excluded.
- Keep limited users and public APIs out of scope. Never write imported values into statements.
- Show detailed reconciliation in the new section and a compact monthly status on the Statements page.
- Use existing OpenRouter settings and do not modify any `.env*` file.

## Accepted Decisions

- One bank statement belongs to one active store.
- Card expected net is 99% of card revenue.
- Wolt and Foodora expected net is 70% of their channel revenue.
- Bolt expected net is Bolt revenue minus 35% of Bolt plus Bolt Cash revenue.
- Marketplace payout periods are AI suggestions that require administrator review.
- Confirmed bank data is immutable until explicitly reopened; reconciliation remains live.
- Original PDFs are retained privately without automatic expiration.
