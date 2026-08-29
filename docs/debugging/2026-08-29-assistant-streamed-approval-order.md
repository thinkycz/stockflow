# Assistant confirmation appears above streamed text

## Symptom

During the original streamed response, a completed confirmation card remained above the assistant's later explanation. Refreshing the conversation moved the confirmation to the bottom, making the live and hydrated layouts visibly inconsistent.

## Evidence

- The browser renderer previously anchored a grouped confirmation at the first `write_*` tool part in the provider event order.
- Providers may emit tool-call and approval events before a later text part in the same assistant message.
- Persisted conversation hydration serializes the completed text before the approval parts, so refresh appeared to repair the order.
- A focused browser fixture emitting `tool approvals → text → finish` reproduced the confirmation before the text and inside the same `<article>`.

## Root cause

The UI treated provider event order as presentation order. Tool lifecycle parts and conversational text share one SDK message, but a confirmation is a user interaction surface and must have a stable presentation position independent of when its event arrived.

## Fix

- Render normal assistant content without business-approval parts.
- Render all business confirmations in a separate following message after the normal content.
- Keep incomplete write proposals hidden while the latest response is still streaming.
- Preserve historical and running confirmation states while later messages stream.
- Apply the rule to both single and grouped `write_*` confirmations; clarification choices remain normal assistant content.

## Verification

- The regression fixture now asserts that the explanation and confirmation have different article containers and that the confirmation follows the explanation in document order.
- All 13 assistant Playwright scenarios pass, including delayed streaming, 30-action grouping, single approval, rejection, reload/resume, and mobile layout.
- A signed-in Chrome inspection of an existing multi-shift conversation shows the explanatory response followed by a separate confirmation message. No action was approved or cancelled during verification.

## Guardrail

Never derive the visual order of interactive assistant controls from provider event order. Normalize lifecycle parts into stable user-facing regions before rendering.
