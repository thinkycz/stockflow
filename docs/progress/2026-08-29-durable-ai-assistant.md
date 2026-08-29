# Durable AI Assistant Delivery

## Goal

Make every assistant read explicitly complete or paginated, and make assistant
turns survive browser/proxy disconnects without repeating approved mutations.

## Evidence

- Production `read_shifts` returned at most 50 newest rows without pagination or
  truncation metadata, causing early-month shifts to be treated as absent.
- All 20 read resources now own their typed datasets, tenant-scoped queries,
  direct details, filters, and summaries. They share only the bounded result
  envelope, cursor, cancellation, safe-error, and audit mechanics.
- Laravel AI persists canonical conversation rows only after a streamed response
  finishes.
- The assistant UI hides completed `read_*` tool parts.
- Laravel AI's default conversational trait supplies only the latest 100 stored
  rows to the model.

## Delivery Slices

- [x] Add regression coverage for incomplete reads and the September shift case.
- [x] Introduce the versioned read envelope, opaque cursor, byte budget, and
      resource-specific filters/summaries across all read resources.
- [x] Add durable queued turns, ordered stream events, reconnect/cancel endpoints,
      idempotency, stale-turn recovery, and bounded conversation context.
- [x] Reconnect the Vue client automatically and retain completed read status.
- [x] Update ADR/verification documentation and pass focused tests, `make fix`,
      `make check`, and `make e2e`.

## Constraints

- Keep 20 stable `read_*` / `write_*` pairs and `ask_user_choice`.
- Keep Laravel native tool execution, approvals, conversation storage, and audit
  lifecycle authoritative.
- Keep OpenRouter `minimax/minimax-m3:free` as the sole provider/model.
- Never repeat an approved mutation when a stream, provider continuation, or job
  fails.

## Current State

- Active slice: complete.
- Blockers: none.
- Worktree was clean at start on `main` tracking `origin/main`.

## Implemented Evidence

- All 20 provider-facing reads are deep concrete `read_*` classes. The former
  central `AssistantDataQueryService`, `AbstractCatalogReadTool`, and shallow
  metadata dispatch path are removed.
- List responses use the version 2 completeness envelope, fetch one extra row,
  expose encrypted 30-minute cursors bound to actor/resource/filters, and remain
  under a 64 KiB encoded budget.
- Exact shift-month summaries cover rows beyond page one, merge adjacent worker
  intervals, list days without shifts, and disclose when full opening-hours
  coverage cannot be determined.
- Durable message and approval turns are queued separately, journal typed Vercel
  events encrypted at rest with coalesced text deltas and a terminal finish
  event, replay by stable turn UUID, reject conflicting concurrent turns, and
  retain failed input for retry.
- The Vue client supplies UUIDs, resumes active streams, retries transient
  reconnects, cancels server-side, hydrates queued user text, and preserves a
  compact completed-read status.
- Conversation context now keeps up to 300 stored rows / 500,000 serialized
  characters in complete semantic turns and maintains text/action memory for
  older rows without copying raw live tool results.
- Durable queued turns are mandatory whenever the assistant is enabled. Failed
  turns use an explicit child retry; recovery after a successful mutation is
  continuation-only and cannot replay the mutation.

## Verification at Initial Durable-Turn Delivery

These counts describe the initial durable-turn delivery. The current deep-reader
refactor is verified in
`docs/verification/2026-08-28-main-admin-ai-assistant.md` so historical counts
are not mistaken for the current suite.
