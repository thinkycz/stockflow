# ADR 0008: Approved AI actions and provider boundary

## Status

Accepted 2026-08-28.

## Context

The main administrator needs a conversational interface that can inspect live Stockflow data and perform the same operational work as the existing Inertia UI. Model-selected actions create a new authorization, replay, privacy, and accountability boundary that does not exist for ordinary controller submissions.

## Decision

- Only the main admin may access assistant conversations or execute assistant tools.
- Every state change and external side effect pauses for Laravel AI native human approval.
- The provider receives 20 stable resource-level read/write pairs (40 business tools), including `read_shifts`, `write_shifts`, `read_workers`, and `write_workers`, plus the non-mutating `ask_user_choice` UI-control tool. It does not receive a generic operation name, JSON envelope, class name, URL, or dispatch endpoint.
- Each writer publishes a Laravel JSON Schema `anyOf` request with one closed branch per supported action. Action, store, target, ownership context, related-record IDs, and fixed-row identities are locked.
- Business approvals are read-only confirmations. Their public decisions contain only approve or reject; browser clients cannot submit edited arguments, rejection text, operation names, record IDs, store IDs, ownership data, or authorization context.
- When one assistant response proposes multiple writes, the browser presents one bounded review containing every localized action summary. One Perform or Cancel click resolves every displayed native approval consistently and submits their distinct tool-call decisions in one continuation request. This grouping applies to all `write_*` resources, never includes clarification choices, and never authorizes hidden or future actions; authorization, validation, execution, replay protection, and audit records remain per tool call.
- Version 2 action previews expose only a localized business-summary key, bounded scalar interpolation values, and optional read-only named rows. `AssistantActionPresenter` formats this display contract but never authorizes, validates, or executes an action.
- Closed-set clarification uses `ask_user_choice` with two to four server-persisted options. A public option selection is validated against the pending call and translated server-side to Laravel's native edited decision so the original locked arguments are retained. The choice result only resumes the conversation and never approves or performs a business mutation.
- Assistant text is rendered as sanitized Markdown with raw HTML, unsafe links, scripts, iframes, and remote images disabled. User text stays plain and provider reasoning is not displayed.
- An approved tool invokes the same application command/service as the equivalent human action. Tools never persist domain models directly.
- Existing domain records and transactional operational activities remain canonical. A separate sanitized assistant action ledger records tool proposals, decisions, execution, outcomes, and timing for 90 days.
- Conversations persist until the main admin deletes them; deleting a conversation does not delete its assistant audit rows.
- Read tools return a versioned completeness envelope. A bounded page is never evidence that no other record exists: encrypted cursors bind actor, resource, normalized filters, and snapshot time, while exact summaries answer aggregate questions without page truncation. Provider results are capped at 50 records and 64 KiB.
- Assistant turns are created before provider work and run on the dedicated Redis `assistant` queue. Encrypted event rows are the resumable delivery journal; Laravel AI conversation rows, native approvals, domain records, and assistant action audits remain authoritative rather than being replaced by that journal.
- Client-generated turn UUIDs make duplicate submissions idempotent. Browser or proxy disconnects only detach the event tail and do not cancel the provider job. Cancellation is explicit, checked before tool invocation, and never reverses a domain mutation that already completed.
- Conversation context retains complete recent semantic turns (up to 300 stored rows and 500,000 serialized characters) and a bounded older text/action memory. Raw historical live-data tool values are omitted from memory and current-state questions require a fresh read.
- OpenRouter with `minimax/minimax-m3:free` is the sole configured provider and model. The accepted boundary includes processing by OpenRouter and whichever eligible upstream provider serves the requested model under the deployment account's data-policy settings.
- No web, MCP, filesystem, shell, arbitrary SQL, hosted tool search, or nested mutation agent is exposed.
- `AssistantToolCatalog` only constructs tools, resolves persisted native names, and exposes capability metadata for route-parity tests. It does not authorize, validate, preview, or execute business actions.

## Consequences

New application mutations must map to one native writer action or be explicitly classified before route-parity checks pass. Invalid proposals return a bounded repairable tool result without producing an approval card or domain write. A grouped confirmation is a presentation and decision-submission boundary, not a cross-domain transaction; each approved native action retains its existing service transaction and independent result. Provider outages make the assistant unavailable but do not affect ordinary Stockflow workflows. Durable event rows are retained for 24 hours, while abandoned turns are marked failed by a scheduled watchdog. Production enablement requires the assistant migration, a one-attempt Forge worker for the `assistant` queue with a timeout above provider generation, and `AI_ASSISTANT_DURABLE_TURNS=true`.

## Accepted risk

Operational, personnel, payroll, and financial context required for a request may be processed outside Stockflow by OpenRouter and a routed upstream provider. Payload minimization, bounded query results, secret redaction, explicit admin access, fixed model selection, and deployment review of OpenRouter data-policy settings limit—but do not remove—that risk.
