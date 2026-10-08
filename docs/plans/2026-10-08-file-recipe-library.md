# Recipes in Markdown

## Agreed outcome

54 recipe documents, nine categories, 189 explicit variants. Recipes are maintained
only in code. All creation, editing, category management, ordering, archiving,
staff tests, and historical recipe-test results are removed. The database migration
drops the recipe catalog and test tables and the three user initialization markers.
Existing unrelated gift-voucher output changes are preserved.

The reviewed code catalog is the source. Prices are omitted. The five hot drinks
retain their gram measurements; `sp` means standard scoops. Recipe lookup appears
on recipe pages only. The library includes a visual reference and optional guided
preparation. Interface translations remain synchronized across English, Czech,
and Slovak. Recipe source documents use English; the rendered catalogue follows
the employee language through synchronized content dictionaries.

## Delivery

- [x] Convert and standardize the 49 recipes; add the five hot drinks.
- [x] Add the validated Markdown repository and shared assistant reads.
- [x] Remove database catalog/testing behavior and add the drop migration.
- [x] Deliver autocomplete, visual reference, variants, and guided preparation.
- [x] Replace retired-feature checks with catalog, migration, lookup, and UI checks.
- [x] Run formatting, the full gate, and visual browser verification.

## Evidence

Verified on 9 October 2026:

- `make fix` and `make check` completed successfully. PHPStan remains at its maximum
  level; formatting, dependency audits, frontend types/build, and deployment cache
  checks passed. Audits reported no vulnerabilities.
- PHP checks: 1,298 passed, with 21 existing environment-dependent skips. Frontend
  unit checks: 148 passed. Browser checks: 94 passed.
- All 184 existing variants retain the original catalog's measured quantities,
  units, and action order, verified against independent source signatures. The
  five hot variants have explicit quantity checks, including two dried strawberries
  for Strawberry Cloud and standard scoops for Taro Milk Tea.
- The removal migration was tested against a populated isolated SQLite database.
  All eight recipe/test tables and three initialization markers were removed;
  unrelated users, stores, workers, and inventory remained intact.
- Browser verification covered lookup from two letters, duplicate names,
  keyboard selection, variant combinations, topping guidance, ingredient
  checklists, previous/next/restart, and timer pause/resume/expiry/reset.
- Desktop and 390 px mobile screenshots were visually reviewed. Ingredient
  amounts and both preparation tabs also passed visual review and overlap checks
  at 320 px in English, Czech, and Slovak. Screenshots are in
  `output/playwright/recipes-*.png`. After making captures wait for visual
  transitions, all eight recipe browser checks passed again.
- Existing unrelated gift-voucher PDF and screenshot changes were restored
  byte-for-byte after the browser suite generated its outputs.

Local MySQL still refuses connections. The drop migration has **not** been applied
to the local application database; it will run through the normal deployment
migration workflow. All automated database checks used isolated test databases.

The [9 October recipe logic audit](../verification/2026-10-09-recipe-logic-audit.md)
records subsequent ingredient and method clarifications, four explicitly approved
formula corrections, and the replacement of category buttons with a category
side panel. The original signatures remain available for comparison.

The [follow-up consistency review](../verification/2026-10-09-recipe-consistency-review.md)
standardizes every ice component, localizes the full catalogue, replaces the
sweetness table, and verifies all variants in each language in Chromium.
