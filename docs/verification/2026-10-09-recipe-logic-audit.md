# Recipe logic audit — 9 October 2026

## Scope and source

Reviewed all 54 recipes, nine categories, and 189 variants. Compared the Markdown
documents with the original curated catalog in commit `f93ea53`, and checked
ingredient completeness, measured amounts, units, method order, variant selectors,
batch proportions, brewing endpoints, equipment, and ingredient icons.

The ice variants were not swapped. The old catalog represented a full cup of ice
as a method action, while representing the 2–3 chilling cubes in no-ice drinks as
an ingredient. The Markdown conversion copied the structured ingredients, so
iced recipes omitted ice from their ingredient lists. The user confirmed that
iced recipes should explicitly list a full cup of ice.

## Corrections

- Listed ice in all 85 iced-drink variants and all seven tea-preparation variants
  that use ice for cooling and dilution. Shaker drinks measure a serving cup of
  ice before transferring it to the shaker. No-ice variants retain their authored
  2–3 chilling cubes and their own liquid and sweetener quantities.
- Listed all 81 final top-ups separately, using the appropriate milk, coconut
  water, or brewed tea. Moved 75 top-ups to follow base assembly. Top-ups happen
  in the serving cup, with space reserved before adding a cloud or finishing tea
  layer. This accounts for matcha and fruit already added to the drink.
- Listed milk and coconut milk in all eight cold taro variants. The 300 ml or
  400 ml amount denotes the total mixture volume, not a fixed milk quantity.
- Made tea-bag immersion and removal explicit; clarified that multiple tea
  varieties are divided between the same bags. The 3.5 L oolong batch uses two
  bags and the 1.5 L batch uses one. Loose tea is strained before adding ice.
- Made tapioca draining explicit before its sauce is added. Ingredients now
  distinguish cooking water, sauce, and rinsing water. Added the required
  colander, strainers, lids, stirring spoons, and muddlers where used.
- Corrected the icon match that mistook fruit names containing `slice` for ice.
  Clarified unmeasured garnish wording and singular scoop instructions.
- Replaced ten category filter buttons with one "Browse categories" control in
  the search toolbar. It opens a side panel with illustrated category tiles and
  recipe counts. Choosing a category closes the panel and filters immediately;
  the selected category remains visible on the control and persists on reload.
  Autocomplete continues to find recipes across the catalog. The controls stack
  on phones; the category panel scrolls independently and restores focus when
  dismissed or after a selection.

## Formula decisions confirmed by the user

Each decision was asked separately with options.

| Preparation                | Confirmed correction                                                                                                                   |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Hojicha Cloud              | Whip hojicha powder directly into the cream and milk, as for Matcha Cloud; remove the unsupported water-preparation instruction.       |
| Cream Cheese, one portion  | 50 ml cream, 20 ml Salko, 20 g cream cheese, 18 ml milk; exactly one-fifth of the batch.                                               |
| Butterfly Tea              | Steep until deep, dark blue, then strain; use a visual endpoint without inventing a timer.                                             |
| Oolong Milk Tea, 1.5 L     | 30 g oolong, 13 g Ceylon, 386 g milk powder, 1.07 L water at 90 °C; add ice to 1.5 L. Quantities round the larger batch's proportions. |
| Black Tapioca, 700 g sauce | 210 g sugar, 140 ml water, 42 ml brown sugar syrup.                                                                                    |
| Black Tapioca, 1 kg sauce  | 300 g sugar, 200 ml water, 60 ml brown sugar syrup.                                                                                    |

All other authored measured quantities and units remain intact. Original fixture
signatures are retained for the four variants with approved numeric corrections.
For source-action parity, the fixture records each original top-up index; the
comparison restores that index while separate regression checks verify the new
assembly order. Supplemental ingredient rows describe additions already present
in the method and do not invent numeric amounts.

## Verification

- [x] Reproduce missing ice, missing taro milk, premature/unlisted top-ups, and
      fruit-slice icon errors with failing checks before applying corrections.
- [x] Verify all original catalog signatures, with explicit approved corrections.
- [x] Verify ice amounts, taro mixture volumes, final assembly order, tea-bag
      counts/removal, tapioca draining, cloud preparation, and cheese proportions.
- [x] Run `make fix` and the full `make check` gate.
- [x] Verify the corrected ice variants and variable ingredient amounts in the
      browser and visually inspect mobile screenshots.
- [x] Verify category selection, combined search, clearing filters, and reload
      persistence on desktop; verify the compact toolbar in all three locales
      at 320 px.

Final `make check` completed successfully: PHPStan at maximum strictness,
formatting, dependency audits, TypeScript, the frontend build, production cache
smoke checks, 1,304 PHP checks (21 existing environment-dependent skips), 148
frontend unit checks, and 95 Chromium browser checks. All nine recipe browser
checks also passed independently after correcting a check that mistakenly
counted the application store selector alongside recipe search.

The category panel was checked for selecting the last category after scrolling,
selected-state feedback, immediate filtering, refresh persistence, Escape
dismissal, and focus restoration after choosing or dismissing a category.
Desktop and 320 px screenshots were visually reviewed in English, Czech, and
Slovak. The corrected Classic Matcha ice checklist was visually reviewed at
390 px. Evidence is in `output/playwright/recipes-*.png`, including
`recipes-categories-desktop.png`, `recipes-categories-{en,cs,sk}-small-mobile.png`,
`recipes-library-{en,cs,sk}-small-mobile.png`, and `recipes-classic-ice-mobile.png`.
Unrelated voucher previews were restored byte-for-byte after the full browser
suite and remain outside the recipe changes.

This audit verifies authored recipe consistency and the application workflow.
It does not claim that the drinks have been physically prepared or taste-tested.
