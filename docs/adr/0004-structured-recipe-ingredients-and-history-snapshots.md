# ADR 0004: Structured ingredients and immutable recipe-test snapshots

## Status

Accepted — 2026-08-02

## Context

The first recipe catalog stored every PDF line as one free-text step. That made a
line such as `100g milk + 20g sugar - stir` difficult to scan and caused ingredient
text to be mixed with the procedure tested for a worker. The follow-up also needs
to preserve historical attempts when an administrator corrects an icon, amount,
or procedure later.

## Decision

- Add `recipe_ingredients` as an ordered child of `recipe_variants`. A row stores
  normalized numeric quantity when possible, exact fallback quantity text, unit,
  name, icon group, and source wording.
- Add `action_key` and `source_text` to `recipe_steps`. The importer uses curated
  deterministic rules for common actions and assigns neutral fallback values for
  ambiguous lines; administrators can override both icon group and action in the
  editor.
- Keep the original `correct_steps` JSON column unchanged for compatibility. New
  attempts additionally store `variant_snapshot`, containing the complete
  ingredient and procedure representation used by the test. Old attempts with a
  null structured snapshot are rendered through their original step snapshot.
- The test service shuffles and scores procedure-step tokens only. Ingredients are
  displayed as a fixed, non-draggable list.
- The index renders all variants inline inside category sections. The detail route
  remains a focused view and the admin editor is the canonical mutation surface.

## Consequences

- PDF import remains idempotent and admin changes are not overwritten.
- Historical result pages can display both legacy and structured attempts without
  recalculation.
- A small amount of duplicated display metadata is intentional: snapshots are
  self-contained and survive deletion of source recipe, worker, or account links.
