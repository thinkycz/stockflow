# Assistant failed-stream persistence investigation

## Symptom

In production, an assistant answer was visibly streamed and then disappeared after
reload. The conversation instead showed “Odpověď asistenta byla přerušena. Můžete
ji bezpečně zopakovat.” The originating user message appeared three times with
out-of-order timestamps.

## Evidence

- Chrome inspection of conversation
  `01a04f6d-6e89-7106-bd74-0287e74ad06b` showed one completed assistant answer,
  followed by three visible copies of `celkový audit udělej` and a latest failed
  turn banner.
- The browser console contained no frontend exception.
- `RunAssistantTurnJob` journals each `TextDelta` before the native stream
  finishes, but `ConversationRepository::assistantPayload()` hydrates only native
  conversation rows plus the failed turn's user input. It does not hydrate the
  failed turn's journaled assistant text.
- Laravel AI `RememberConversation` stores both the user and assistant rows only
  from the stream completion callback. A provider or lifecycle failure after
  visible deltas therefore leaves no canonical assistant row.
- Retry turns retain the original message as their input. Current hydration
  appends that input unconditionally, even when the same logical user message was
  persisted by an earlier attempt in the retry lineage.
- The job performs context validation and summary refresh around the completion
  transition. A derived post-stream failure can currently produce an error event
  after the native conversation has already persisted successfully.

## Root cause

The durable event journal and canonical Laravel AI conversation storage have no
explicit reconciliation rule for failed turns. A partial answer exists in the
journal but is discarded by reload hydration. Retry lineage is also ignored when
deciding whether to add an optimistic user message, so a logical user message can
be rendered more than once. Finally, non-critical post-persistence work is able to
contaminate an otherwise completed turn's visible lifecycle.

## Fix contract

- Hydrate bounded journaled assistant text for the latest genuinely failed turn
  when no canonical assistant row exists for that attempt.
- Resolve the root retry input and show it optimistically only when an equivalent
  canonical user row does not already exist for that lineage.
- Treat completion of the native Laravel AI stream (including its persistence
  callback) as the authoritative success boundary. Derived title, integrity, and
  rolling-memory work must not downgrade or append an error to a completed turn.
- Keep a latest real failure retryable and preserve all historical turn and audit
  rows.

## Verification status

Implemented and covered at every reconciliation boundary:

- Failed-turn hydration reconstructs bounded assistant text from durable
  `text-delta` events and retains one logical user message.
- Retry persistence reuses the original native user row and filters historical
  duplicate rows from presentation without deleting audit history.
- Retry context omits the already persisted logical prompt before Laravel AI
  appends the replay prompt, so the model receives it exactly once.
- A native response persisted before derived context maintenance fails remains a
  completed turn and does not emit a misleading interruption error.
- The frontend reconciles its live stream with canonical Inertia messages when a
  turn finishes.

Fresh focused verification on 2026-08-29:

- Assistant backend suites: 94 passed, 1 skipped, 1,222 assertions.
- Frontend type-check and production build passed.
- Failed-turn browser regression passed after rebuilding the production asset.
- Repository-wide `make check` passed: PHPStan, formatting, dependency audits,
  type-check, production build, 77 frontend unit tests, and 874 PHP tests with
  29,039 assertions (one skipped).
- Chrome reload of the built local application retained exactly one failed user
  message and one journaled partial assistant answer, with the legitimate retry
  control visible.
- Chrome hydration of a successfully recovered conversation showed one canonical
  answer, no stale retry input, and no interruption banner or retry control.

Production still requires deployment of this working tree and an assistant queue
worker restart before the original conversation can use the reconciliation rules.
