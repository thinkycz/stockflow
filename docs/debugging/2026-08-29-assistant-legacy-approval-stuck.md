# Assistant approval stuck on “Finishing action”

## Symptom

A persisted shift approval in the main-admin assistant rendered an indefinite “Finishing action…” status after the approval UX redesign. Reloading the conversation did not reveal the approval buttons.

## Evidence

- The signed-in Chrome page consistently reproduced the pending status without browser console errors.
- The local conversation database retained one unresolved `write_shifts` call for `create_shift`; no domain execution or tool result had occurred.
- Its native approval reason used preview version 1, while the redesigned card recognized only version 2 `action_confirmation` and version 1 `choice` previews.
- A focused Playwright fixture using a persisted version 1 worker approval failed because the confirmation title and buttons were absent.

## Root cause

`AssistantApprovalCard.vue` mounted every `approval-requested` tool call, but an unrecognized version 1 business preview resolved to `null`. The card's fallback branch treated that pending approval like a running tool and displayed the indefinite progress state instead of a decision surface.

## Fix

- Convert recognized version 1 business previews into the current read-only action-confirmation presentation in the browser.
- Build summary parameters only from bounded scalar display values, omit identifier fields, and use existing localized action summaries.
- Keep the original native tool input and pending approval unchanged; the adapter is presentation-only and cannot edit or execute arguments.
- Render an explicit cancel-only recovery message for any unknown pending preview instead of an indefinite progress indicator.
- Retain a persisted version 1 approval in the E2E fixture so future approval-card refactors cover this hydration boundary.

## Verification

- The focused regression failed before the fix and passed after it.
- TypeScript and the production frontend build passed.
- Reloading the exact signed-in Chrome conversation now shows the localized shift summary with **Perform** and **Cancel** buttons.
- No approval was submitted during diagnosis or verification.

## Recurrence prevention

Every persisted approval-preview version must either render an actionable decision surface or a bounded recovery state. Pending approvals must never fall through to an unbounded running indicator.
