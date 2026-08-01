# UI consistency implementation

## Goal

Unify equivalent controls, states, navigation and dialogs across every Vue
surface while preserving the distinct authenticated, public, auth, calendar
and print layouts.

## Phases

1. Extend shared UI primitives and add filter, back-navigation, checkbox and
   dialog building blocks.
2. Migrate page consumers: filters, shifts controls, labels, metrics, empty
   states, alerts and subordinate navigation.
3. Replace native confirmation and prompt APIs with the accessible dialog
   service.
4. Add contract and browser coverage, update frontend guidelines and run the
   complete verification suite.

## Invariants

- Existing routes, backend payloads and database schema do not change.
- Current uncommitted filter/header work remains the source of truth.
- Specialized calendar, tab, editor-choice, upload and combobox controls may
  retain purpose-built markup, but must share focus and accessibility rules.
- Native checkboxes, regular text inputs and selects are allowed only inside
  shared UI primitives.

## Acceptance

- No direct `window.confirm` or `window.prompt` remains.
- Equivalent UI roles use the shared components named in the plan.
- Desktop and 390 px browser coverage has no page-level horizontal overflow.
- Type-check, build, unit tests, relevant E2E scenarios and `make check` pass,
  or environment-only blockers are recorded explicitly.
