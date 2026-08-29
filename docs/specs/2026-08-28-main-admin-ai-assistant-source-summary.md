# Main-Admin AI Assistant – source summary

## Source artifacts

- Approved implementation plan supplied in the 2026-08-28 Codex conversation.
- Laravel AI SDK 0.11 documentation for conversations, streaming, tools, approvals, and events.
- Laravel AI's native OpenRouter provider and the OpenRouter MiniMax M3 free model catalog.
- Existing Stockflow routes, controllers, services, ADRs, UI primitives, tests, and repository guidelines.
- Laravel Inertia Stack reference task `019fef6f-a4ab-7813-a09c-518d7157f6b6` for transport and approval UI patterns only.

## Normalized requirements

- Add a dedicated admin-only AI Assistant destination with persistent streaming conversations.
- Answer bounded questions from live Stockflow data across all company stores; use the active store for “current store”.
- Expose every current business mutation available to the main admin, except authentication/password/email-verification, credential/provider configuration, and new binary uploads.
- Require native Laravel human approval before every state change or external side effect.
- Permit approval-time edits only for explicitly safe business fields; lock action, store, target, ownership, and authorization context.
- Execute the same application command/service as the human UI so domain records, lifecycle rules, transactions, journal entries, notifications, and reversals stay identical.
- Persist conversation history until manual deletion and persist a sanitized AI action ledger for 90 days independently of conversations.
- Use Laravel AI `^0.11.0`, Vercel AI `^7.0`, `@ai-sdk/vue ^4.0`, and the native OpenRouter driver with `minimax/minimax-m3:free`.
- Keep provider processing documented in ADR 0008: prompts may include operational, personnel, payroll, and financial data and are subject to OpenRouter plus the routed upstream provider's configured data policy.

## Scope boundaries

- No web search, MCP, filesystem, shell, arbitrary SQL, hosted tool search, or mutation sub-agents.
- No application-level model/provider fallback in the first release. OpenRouter remains responsible for routing the requested model among eligible upstream providers.
- No chat file attachments. Removing an existing noticeboard image or voucher logo remains supported when the human workflow supports removal.
- Login/logout, password changes, email verification, database/API credentials, and AI-provider configuration remain human-only.
- Existing route and domain authorization remains authoritative after approval; approval never bypasses validation or lifecycle rules.

## Source-of-truth decisions

- The approved plan controls feature behavior.
- `docs/guidelines.md`, `AGENTS.md`, and existing ADRs control implementation conventions and domain invariants.
- Existing services and human controller behavior control mutation semantics.
- When a controller currently writes directly, a shared application command is extracted and both callers use it before the assistant action is considered supported.

## Missing external prerequisites

- A real `OPENROUTER_API_KEY` is not present in source control and is not required for automated tests.
- Live provider smoke testing is deployment work; CI and local automated tests use Laravel AI fakes.
