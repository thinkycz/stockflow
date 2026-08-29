# Main-Admin AI Assistant – implementation plan

## Phase 1: Foundation and persistence

- Install Laravel AI and Vercel AI dependencies.
- Add typed OpenRouter configuration, SDK conversation schema, audit schema/model/factory, and 90-day prune job.
- Add the accepted architecture decision and foundational tests.

## Phase 2: Agent, transport, and vertical tracer

- Add the conversational Stockflow agent, explicit provider/model binding, persisted message serialization, and admin-only endpoints.
- Implement one bounded read operation and one approvable stock-movement operation through existing services.
- Prove ownership, approval, audit, rejection, replay, and failure behavior.

## Phase 3: Shared operation registry and mutation parity

- Create typed operation definitions with validation, authorization, preview, editable-field, store/target resolution, and execution contracts.
- Map read and mutation capabilities by domain.
- Extract shared commands where existing controllers write directly, then route both the human controller and assistant operation through the same command.
- Complete and enforce the authenticated mutation parity matrix.

## Phase 4: Admin Inertia experience

- Add the Management navigation destination and responsive assistant page.
- Implement persistent conversation navigation, Vercel streaming, stop/error/retry states, native approval parts, safe-field editing, and deletion. Provider processing remains documented in ADR 0008 without an inline chat notice.
- Synchronize English, Czech, and Slovak translations.

## Phase 5: Verification and release readiness

- Complete backend, frontend, parity, audit, concurrency, retention, and regression tests.
- Run formatting, static analysis, lint, audit, type-check, build, unit/feature suites, E2E, and browser smoke.
- Update progress, traceability, verification evidence, blockers, and final release verdict.

## Phase 6: Native resource-tool architecture

- Replace the generic read and mutation routers with concrete Laravel AI read/write tools grouped by application resource.
- Add typed action-specific schemas, versioned approval previews, typed approval editing, bounded preflight repair, and a non-executing tool catalog.
- Remap all authenticated mutation routes to native tool/action capabilities, remove the old operation registry and broad executors, and preserve human-service equivalence.
- Re-run focused approval/parity tests, full checks, E2E coverage, and the opt-in provider smoke contract.

## Phase 7: Rendering and approval UX redesign

- Render assistant text through one safe Markdown component while keeping user messages as plain text and provider reasoning hidden.
- Replace editable technical approval panels with localized read-only business confirmations and approve/cancel decisions only.
- Add the native `ask_user_choice` clarification tool with server-locked options and a select-only continuation contract.
- Make streamed tool progress visible until complete approval or choice controls arrive, preserve near-bottom scrolling, and hydrate the same controls after reload.
- Verify the public decision boundary, localized presenter coverage, Markdown safety, delayed stream transition, mobile/accessibility behavior, and full regression gates.
