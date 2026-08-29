# Main-Admin AI Assistant – verification record

## Claim

The main-admin assistant is implemented and ready for code review and deployment configuration. Production enablement remains intentionally off until an environment supplies an OpenRouter key and passes the opt-in live smoke test.

## Requirement evidence

- Access and ownership: every page, load, prompt, continuation, and deletion resolves through the authenticated main admin and owned conversations.
- Streaming and persistence: new and existing conversations use Laravel AI persistence and return Vercel-compatible text, tool, approval, result, and error parts with the conversation ID header.
- Durable delivery: when enabled, each submission is stored before generation, executed once on the Redis `assistant` queue, and tailed from an encrypted typed event journal. Consecutive text deltas are coalesced and the finish event is always last. Reload and transient disconnects reconnect by stable turn UUID; queued user text remains visible and canonical SDK messages reconcile after completion.
- Complete reads: all 20 concrete read tools return explicit complete/partial metadata, encrypted filter-bound cursors, and bounded results. Exact shift-month aggregation covers records beyond the first 50 and returns merged daily intervals and missing days; assistant instructions forbid negative/exhaustive claims from partial pages.
- Approval boundary: every mutation or external effect implements native approval; the browser can only approve or cancel and cannot submit edited arguments, technical context, or rejection text. Multiple writes proposed in one response are shown in one complete review and one click submits a distinct locked decision for every displayed tool call.
- Human parity: application operations call shared domain services used by the web controllers. Normal records, reversals, snapshots, operational activities, notifications, and transaction boundaries remain authoritative.
- Audit and replay: the supplemental sanitized ledger follows native lifecycle events, uses a unique conversation/tool-call key, serializes execution per conversation, and never repeats a completed or failed domain command.
- Capability boundary: the non-executing native catalog exposes 20 stable read/write resource pairs. Every writer has its own final native tool class and stable `write_*` identity; the catalog does not choose tools through a provider-facing operation router. Every current authenticated business mutation route maps to one declared writer action or records an explicit read-only/excluded classification.
- Runtime controls: prompt, step, timeout, rate, result-size, and 90-day retention limits are configured and tested.
- UX: the assistant sits directly below Workers in Management. Its full-height, full-bleed workspace places a dedicated conversation rail beside the application sidebar, while mobile exposes the same history in a drawer. Assistant text renders sanitized Markdown while user text remains plain, reasoning stays hidden, mutation proposals use localized read-only summaries with only Perform/Cancel controls, all writes in one response share one bounded review and decision surface, closed-set clarifications render as locked A/B/C/D choices, and incomplete streamed tools show an activity state until their complete card is ready. Provider processing is documented in ADR 0008 rather than shown inline in chat.

## Automated evidence

- `make fix` completed successfully.
- `make check` completed successfully with PHPStan at max, formatting checks, zero Composer/npm audit findings, platform and Composer validation, frontend type-check/build, 75 Vitest tests, and 842 passing Pest tests / 28,270 assertions. The opt-in live provider smoke is the sole intentional skip.
- Assistant-focused coverage includes access, ownership, validation, throttling, stream headers, persistent approvals and choices, read-only approve/reject decisions, grouped multi-resource decisions, locked option selection, missing/unknown/stale decisions, lock contention, replay, audit redaction/status transitions, provider failures, post-execution generation failures, and retention pruning.
- The architecture suite proves 20 unique final writer classes, serializes every action-specific schema, resolves every supported route to a native tool/action pair, verifies that every declared writer action maps back to a route, and fails on unclassified authenticated mutations.
- `make e2e` completed with 61/61 Playwright scenarios passing. Its 15 assistant scenarios cover navigation placement, full-height desktop geometry, conversation creation/timestamps, an explicit `read_shifts` stream, safe Markdown, delayed proposal progress with buttons appearing without reload, a 30-action single-review continuation, locked clarification selection, bounded server failures, read-only stock-movement approval, cancellation, persisted reload/resume, non-technical worker confirmation, and the mobile conversation drawer.

## Runtime evidence

- `php artisan schedule:list` registers assistant audit pruning daily at 04:30 Europe/Prague.
- Browser tests run with Laravel migrations, deterministic E2E data, a fake assistant provider, and no live credentials.
- A signed-in Chrome inspection confirmed that assistant Markdown renders as semantic headings, lists, code, and links after reload, while technical approval metadata and editable controls are absent from the current UI.
- A repeated live check found and fixed persisted version 1 business approvals falling through to “Finishing action…”. The exact Chrome conversation now hydrates to a localized read-only confirmation with Perform/Cancel, and the E2E fixture retains this compatibility case.
- A second signed-in Chrome check reproduced a 30-shift proposal where the first approved card remained on “Finishing action…” because 29 sibling approvals were unresolved. The same persisted response now hydrates as one 30-row review with one Perform/Cancel surface; no production decision was submitted during verification.
- A streamed-order regression fixture proved that providers can emit approvals before later explanatory text. Business confirmations now render as a separate message after all normal assistant content regardless of event order, matching the refreshed layout. All 13 assistant browser scenarios pass and signed-in Chrome confirms the same ordering for an existing multi-shift conversation.
- Successful mutation state is surfaced from the audit/domain result even when the model follow-up generation fails; its approval is not resubmitted.

## Deferred external evidence

- `OpenRouterSmokeTest` requires `OPENROUTER_SMOKE_TEST=true` and a real `OPENROUTER_API_KEY`; it is intentionally skipped in CI and local default runs.
- Deployment must confirm the native OpenRouter connection, the `minimax/minimax-m3:free` tool-calling path, and accepted OpenRouter/upstream-provider data policies before setting `AI_ASSISTANT_ENABLED=true`.
- Deploy `2026_08_29_000001_create_assistant_turn_tables.php` before enabling durable turns. Add a Forge supervisor for `php artisan queue:work assistant --queue=assistant --tries=1 --timeout=150`, keep Redis `retry_after` above the 120-second provider timeout, then set `AI_ASSISTANT_DURABLE_TURNS=true`. The scheduled watchdog must be visible in `schedule:list`.

## Verdict

Ready for review and deployment configuration. There are no known code, test, parity, audit, or browser blockers. Live-provider connectivity is a deployment prerequisite, not a repository defect.
