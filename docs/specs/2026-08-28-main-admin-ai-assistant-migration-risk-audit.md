# Main-Admin AI Assistant – migration risk audit

## Migration surface reviewed

- New Laravel AI dependency, published configuration, and SDK conversation tables.
- New assistant audit persistence and scheduled pruning.
- New admin-only streaming endpoints and Inertia page.
- Shared application-command extraction across existing mutation controllers.
- New frontend streaming and approval-state dependencies.

## Blast radius and hazards

- **Schema conventions:** vendor migrations may violate the repository’s explicit-column and architecture-test rules. Copy the required SDK schema into application-owned migrations and review it before running tests.
- **Authorization:** Laravel’s `continue` API does not authorize conversation ownership. Resolve conversations through the authenticated admin’s relationship before every load, continuation, or deletion.
- **Behavior drift:** many mature domains already centralize writes in services, while catalog/settings controllers still persist directly. Extract one vertical command at a time and preserve the existing controller tests.
- **Duplicate execution:** concurrent approval submissions can race. Serialize each conversation with a Redis-backed lock in deployed environments (and the atomic file lock store for single-host local development), then rely on stable tool-call IDs plus a unique audit key.
- **Audit coupling:** the AI ledger is supplementary. It must not move existing operational journal writes or notifications outside their current domain transactions.
- **Provider compatibility:** Laravel AI 0.11 includes a native OpenRouter driver. Keep fixed local tools and provider fakes in CI; the opt-in live smoke remains the deployment proof for the configured `minimax/minimax-m3:free` endpoint and its tool-calling support.
- **Architecture gates:** every new controller needs a feature test; new models need `BaseModel`, `querySelect`, `scopeSearch`, casts, explicit getters, factory generics, and complete docblocks.
- **Frontend protocol drift:** persisted Laravel messages must be serialized back into Vercel UI message parts, including unresolved approvals after reload.

## Safe sequencing

1. Lock dependencies and add application-owned schemas/configuration.
2. Prove admin access, conversation ownership, and audit persistence with tests.
3. Add one read tool and one approved mutation tracer before widening the registry.
4. Extract shared commands vertically, retaining existing controller behavior tests.
5. Build and verify the UI against fake streams before a live provider smoke.

## Verification priorities

- Migration and rollback behavior on MySQL and the test database.
- Conversation ownership and duplicate approval protection.
- Human-versus-assistant database equivalence for every mapped mutation family.
- Audit redaction, status transitions, and Prague-local 90-day pruning.
- Vercel stream hydration, approval edits, retry behavior, locale parity, responsive layout, and full `make check`.
