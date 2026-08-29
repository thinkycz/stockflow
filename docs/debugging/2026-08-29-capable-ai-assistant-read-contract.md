# Capable AI assistant read contract

## Symptom

In production, the assistant said it could only see metadata while answering a store profitability question. It could list financial report identifiers and lifecycle state, but not the income, expense, revenue-channel, or margin data visible on the human-facing pages.

## Proven root cause

- `read_financial_reports` and `read_statements` delegated to `AssistantDataQueryService`.
- Their list mappers exposed only report metadata.
- Their summaries delegated to `genericSummary()`, which returned only `record_count`.
- The actual decision data already existed in `FinancialReportService::build()` and `StatementService::buildReport()`.
- The same central switch used shallow mappers for most other resources, so the defect was architectural rather than finance-specific.

## Fix boundary

Each native `read_*` tool becomes resource-specific and owns its schema, filters, authorization, direct detail query, and service-backed summary. Shared code is limited to safe envelopes, keyset cursors, result-size bounding, cancellation, and audit metadata. The generic resource dispatcher is removed.

## Regression evidence

`StockflowDataToolTest` now creates a populated store/month and requires the statement reader to return revenue, channels, and margin facts and the financial reader to return actual income and expense drivers matching the human report service.

## Status

- Reproduction: complete
- Root cause: complete
- Regression test: passing
- Resource reader migration: complete; the central dispatcher was removed
- Durable context/retry hardening: complete
- Route parity and production diagnostics: complete
- Full verification: complete (`make check` and all 61 browser scenarios pass)
