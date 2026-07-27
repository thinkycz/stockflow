# Inventory stepper autosave

## Symptom

Inventory quantities entered into the number field are autosaved after the
field loses focus, but quantities changed through the plus and minus buttons
are lost after reloading the page.

## Root cause

The input and reason/note controls call `autosave` from their blur handlers.
The plus and minus buttons call `adjustQuantity`, which only updates local
reactive state and never starts the draft-row `PUT` request.

## Scope check

The stepper exists only in the inventory editor. Other inventory controls
already have explicit blur autosave handlers, so the defect is local to
`adjustQuantity`.

## Expected fix

Every successful plus or minus adjustment must invoke the same draft-row
autosave path as the other controls. Browser coverage must observe the `PUT`
request and confirm that both directions survive a reload.
