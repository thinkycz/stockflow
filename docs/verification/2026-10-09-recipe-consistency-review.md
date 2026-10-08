# Recipe grouping, language and visual consistency

This follow-up addresses the employee-reported ice grouping, mixed Czech/English
content, and the wide topping-sweetness table. Scope is the complete file catalogue:
54 recipes, nine categories, 189 variants, in English, Czech and Slovak.

## Findings and corrections

The earlier audit added an Ice group for full-cup iced drinks, but retained the
85 original chilling-cube rows inside Drink base / Drink mixture. Seven tea-batch
ice rows used Batch dilution. The content now uses **Ice for all 177 ice rows**,
including chilling and batch dilution. Ingredient components have one documented
relative order and contiguous rows. The catalogue rejects incorrect ice groups
and interleaved or out-of-order components rather than hiding them in the UI.

No quantities or preparation actions changed in this follow-up. Iced drinks retain
a full serving cup; no-ice drinks retain their confirmed 2–3 chilling cubes. Tea
batch ice remains an addition to the authored final total volume. Comparison with
the pre-change snapshot confirms every measurement and every English method,
action and timer across all 189 variants. Historical fingerprints are preserved;
an additional order-independent fingerprint was calculated only after verifying
each previous signature, so component reordering cannot disguise a formula edit.
The five hot drinks retain the exact weights, scoop counts and garnish quantities.

The old interface was localized while the Markdown content remained English.
The previous small-screen checks changed only the interface locale in intercepted
responses, so they did not verify actual translated recipe content. The catalogue
now uses the authenticated account's language for categories, preparation names,
variant labels, ingredients, equipment, methods, notes, tips and links. Official
menu drink names retain their spelling. Original preparation names remain search
aliases and localized ingredient terms are indexed for autocomplete. The assistant
reads the same translated catalogue for its administrator.

Translations live in three synchronized content dictionaries with 498 templates
each. Numeric placeholders reuse the selected Markdown recipe's measurements;
Czech and Slovak display decimal commas. Missing translations and mismatched
placeholders fail validation. All three languages retain stable keys, URLs,
selectors, numeric ingredient values, units, action identities and timer durations.
Every localized instruction is also checked against its original number sequence.
Recipe authoring remains one Markdown file per drink, with translation instructions
in `resources/recipes/README.md`.

The amber accordion and four-column sweetness table are replaced by a compact
card with a keyboard-accessible topping-count selector. It shows one prominent
amount for each sweetener, the base amount for reference, and an explicit
"Leave out" instruction at 0 ml. Changing variants resets the selection; variants
without adjustable sweeteners hide the card. The confirmed rule remains unchanged:
reduce each liquid sugar / syrup in ml by 5 ml for two toppings or 10 ml for three,
with a minimum of zero. The ingredient list and methods retain their base amounts,
which is explained directly on the card. Gram-based hot syrups are unaffected.

## Verification

- [x] Reproduce the ice-group and missing-content-localization failures.
- [x] Verify every previous measurement fingerprint before converting comparison
      to an order-independent digest, and compare all 189 full ingredient sets
      and methods against the untouched pre-change snapshot.
- [x] Load the entire catalogue in all three languages and compare quantities,
      units, keys, selectors, actions, timers and all instruction numbers.
- [x] Change account language through the actual profile form and verify the
      resulting library/detail responses for administrators and limited employees.
- [x] In Chromium, open all 54 recipes and select all 189 variants in **each**
      language: compare every visible ingredient, component and method, then start
      guided preparation and verify its first step and checklist.
- [x] Review localized category panels and checklists at 320 px.
- [x] Review the redesigned Czech card on desktop and at 320 px; verify 5 ml → 0 ml,
      multiple sweeteners, arrow-key navigation, variant resets, and hiding when
      there are no adjustable sweeteners.
- [x] Run final `make fix` and the complete `make check` gate.
- [x] Restore unrelated voucher previews byte-for-byte after the full browser run.

Evidence images are under `output/playwright/recipes-*.png`, including
`recipes-sweetness-cs-detail.png` and `recipes-sweetness-cs-mobile-detail.png`.
The database retirement migration and the previously confirmed formula corrections
remain as recorded in the first recipe audit. This review covers authored and
rendered consistency, not physical preparation or taste testing.

Final validation passed: maximum-level PHPStan, formatting, dependency audits
with no vulnerabilities, TypeScript, the production build/cache smoke checks,
1,309 PHP checks (21 existing environment-dependent skips), 148 frontend unit
checks, and 99 Chromium browser checks. The 13 recipe browser checks include
three full sweeps of all 189 variants, using real saved account locales, and
Czech ingredient lookup without accents. The sweetness card was visually reviewed
at desktop width and 320 px. The two pre-existing voucher preview changes were
restored byte-for-byte and remain outside this delivery.
