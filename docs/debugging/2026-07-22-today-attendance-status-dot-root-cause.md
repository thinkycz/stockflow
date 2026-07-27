# Today Attendance Status Dot Root Cause

## Symptom

Completed attendance rows displayed no status dot in the "Today's attendance" panel.

## Root Cause

The completed branch used `bg-outline`, but the Tailwind theme has no `outline` color token. Tailwind therefore emitted no background-color rule for the element.

## Why It Happened

The valid border token is named `outline-glass`; it was shortened to a nonexistent token when used for the status dot.

## Fix Chosen

Use the existing semantic `neutral` color for the completed status. This keeps outline colors for borders and gives the dot adequate contrast.

## Regression Protection

The production frontend build is checked for the generated `.bg-neutral` utility, and the full project validation covers formatting, type checking, build, and tests.
