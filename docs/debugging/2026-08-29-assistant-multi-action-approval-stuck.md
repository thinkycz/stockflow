# Assistant multi-action approval stuck after the first confirmation

## Symptom

A request for one month of daily shifts produced 30 confirmation cards. Clicking Perform on the first card changed it to “Finishing action…”, but no shift executed and the conversation did not continue.

## Evidence

- The persisted assistant response contained 30 independent native `write_shifts` tool calls.
- The first tool part was `approval-responded`; the remaining 29 were still `approval-requested`.
- The frontend uses the AI SDK completion predicate that resumes only after every approval in the assistant message has a response.
- Chrome had no console error. The client was waiting for the other decisions as designed.

## Root cause

The UI exposed the SDK's per-tool approval granularity directly. The model may issue many native writes in one response, but deciding one card cannot resume that response while sibling approvals remain pending. Rendering each call as an independent user task therefore created an apparent deadlock and an impractical monthly-shift workflow.

## Fix

- Group every `write_*` tool part in one assistant response into one bounded, localized review.
- Keep clarification choices outside the group.
- Hide incomplete write cards while the response is streaming and reveal the complete group after the tool-call response finishes.
- On Perform or Cancel, resolve every still-pending displayed approval with the same decision. The normal SDK continuation then starts after the final local decision.
- Preserve each tool call's native authorization, validation, execution, replay key, audit lifecycle, and domain-service behavior.
- Keep completed group status collapsed into one result instead of showing dozens of running rows.

## Regression coverage

- Unit coverage proves write approvals from different resource tools group together while `ask_user_choice` remains separate.
- Browser coverage streams 30 shift approvals, asserts one Perform and one Cancel button, clicks once, and verifies all 30 locked decisions are submitted in the continuation request.
- The exact signed-in Chrome conversation was refreshed and displayed one 30-row approval group. No approval or cancellation was submitted during verification.

## Guardrail

Approval grouping may include only tool calls already present and visibly summarized in the current assistant response. It must never approve future, hidden, read, or clarification tool calls.
