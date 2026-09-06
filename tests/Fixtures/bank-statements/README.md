# Synthetic bank statement fixtures

`other-bank.pdf` is a two-page text PDF for a fictional bank, in English, with a CZK account, separate debit/credit columns, decimal commas, omitted statement number and source summary counts/totals, and a description continued on page 2. Expected movements are +1000.00 on 2026-08-02 and -250.00 on 2026-08-03; balances are 100.00 and 850.00. Bank code 2010 appears only in the account identifier. All information is synthetic.

`other-bank-scan.pdf` contains rasterized copies of those pages with no text layer. It exercises PDF attachment serialization in automated tests and is available for opt-in live OCR evaluation. A successful mocked provider response is not proof of OCR support; the September 6 live free-parser trial failed safely.

No user bank statement or extracted account data belongs in this directory.
