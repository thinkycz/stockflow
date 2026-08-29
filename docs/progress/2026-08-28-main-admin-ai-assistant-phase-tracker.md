# Main-Admin AI Assistant – phase tracker

## Status

- Current phase: Phase 7 verified
- Overall status: ready for review and deployment configuration
- Last updated: 2026-08-29

## Phase 1: Foundation and persistence

- Goal: Establish dependency, configuration, conversation, audit, scheduler, and ADR foundations.
- Status: verified
- Tasks:
    - [x] Install backend and frontend dependencies.
    - [x] Add typed provider/assistant configuration.
    - [x] Add SDK conversation and assistant audit persistence.
    - [x] Add prune scheduling and foundational tests.
- Blockers: none.

## Phase 2: Agent, transport, and vertical tracer

- Goal: Prove the complete read, approval, mutation, and audit path.
- Status: verified
- Tasks:
    - [x] Add agent and conversation repository.
    - [x] Add admin endpoints and Vercel data streaming.
    - [x] Add bounded read tooling and the stock-movement approval tracer.
    - [x] Verify ownership, approval, rejection, replay, concurrency, and failure states.
- Blockers: none.

## Phase 3: Shared operation registry and mutation parity

- Goal: Cover all approved mutation families through human-equivalent commands.
- Status: verified
- Tasks:
    - [x] Implement typed operation definitions, validation, previews, edit boundaries, and execution.
    - [x] Map inventory, statements, workforce, operations, recipes, finance, vouchers, and administration.
    - [x] Refactor direct-persistence controllers through shared application services where required.
    - [x] Enforce authenticated route parity with an architecture test.
- Blockers: none.

## Phase 4: Admin Inertia experience

- Goal: Deliver persistent responsive chat and native approval UX.
- Status: verified
- Tasks:
    - [x] Add the Management navigation destination and responsive page shell.
    - [x] Add streaming, history, stop, retry, deletion, and persisted conversation hydration.
    - [x] Add independently decidable, read-only approval cards.
    - [x] Add identical Czech, English, and Slovak keys plus frontend tests.
- Blockers: none.

## Phase 5: Verification and release readiness

- Goal: Produce fresh specification, automated, runtime, and documentation evidence.
- Status: verified
- Tasks:
    - [x] Run `make fix`, targeted tests, and `make check`.
    - [x] Exercise read, approval, rejection, reload/resume, cross-store, and mobile browser flows.
    - [x] Run the complete `make e2e` suite.
    - [x] Update traceability, ADR, and verification records.
- Blockers: none for code handoff. The live provider smoke remains an explicit deployment check because no production OpenRouter key is stored in the repository or CI.

## Phase 6: Native resource-tool architecture

- Goal: Replace opaque provider-facing operation routers with typed native read/write resource tools.
- Status: verified
- Tasks:
    - [x] Add the non-executing native tool catalog, 20 unique final writer classes, and shared approvable execution boundary.
    - [x] Migrate shifts and workers as the schema, approval, and typed-editing tracer.
    - [x] Migrate every remaining read resource and all 90 supported mutation routes.
    - [x] Remove provider-facing generic tools, the operation registry, and service-backed operation adapters.
    - [x] Update route parity, monotonic audit lifecycle handling, typed frontend approval rendering, and ADR.
    - [x] Run focused tests, `make fix`, `make check`, and `make e2e`.
- Blockers: none. No deployed conversations require legacy tool compatibility.

## Phase 7: Rendering and approval UX redesign

- Goal: Make responses readable, approvals non-technical, clarifications click-only, and streamed tool progress trustworthy.
- Status: verified
- Tasks:
    - [x] Add safe progressive Markdown rendering and hide provider reasoning.
    - [x] Add localized action-presentation previews and simplified read-only confirmation cards.
    - [x] Remove public business-argument editing and free-form rejection reasons.
    - [x] Add native server-locked clarification choices and select continuations.
    - [x] Add atomic streamed tool progress, stable component keys, near-bottom auto-scroll, and persistent activity controls.
    - [x] Update focused tests, E2E fixtures, translations, ADR, traceability, and verification evidence.
    - [x] Run `make fix`, focused checks, `make check`, and `make e2e`.
- Blockers: none. No deployed conversations require compatibility with the superseded preview contract.

## Decisions

- Every semantic write or external side effect retains its own native approval, authorization, execution, replay key, and audit entry. All write approvals visibly proposed in one assistant response are reviewed and decided together with one click.
- Business approvals are read-only; the browser can only approve or cancel and cannot submit operation arguments.
- Every approved mutation invokes the same application service as its human UI route and therefore retains the normal domain record, operational journal, notification, and transaction behavior.
- Conversation transcripts persist until manual deletion; sanitized assistant action audits persist independently for 90 Prague-local days.
- OpenRouter with `minimax/minimax-m3:free` and its routed upstream-provider data boundary are accepted in ADR 0008.

## Deferred

- Adding or replacing binary images through chat.
- Live OpenRouter MiniMax M3 free native-tool smoke testing with deployment credentials.

## Verification evidence

- `make check`: PHPStan max, formatting, Composer/npm audits, dependency validation, frontend type-check/build, 73 Vitest tests, and 815 Pest tests passed; the live provider smoke is the only intentional skip.
- `make e2e`: 58 Playwright scenarios passed, including all 13 assistant scenarios.
- Assistant approval coverage includes read-only approve/cancel decisions, grouped multi-action decisions, locked choices, missing/unknown/stale decisions, replay/concurrency protection, provider failure, and post-mutation follow-up failure.
- A signed-in Chrome check confirmed progressive Markdown rendering, the adjacent conversation rail, removal of technical approval metadata, and one 30-action review with a single Perform/Cancel decision surface.
- `php artisan schedule:list`: assistant audit pruning is registered daily at 04:30 Europe/Prague.

## Next

- Run the opt-in MiniMax M3 native-tool smoke with deployment credentials before enabling the feature.
