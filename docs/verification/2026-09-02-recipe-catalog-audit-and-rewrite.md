# Recipe Catalog Audit and Rewrite Verification

## Outcome

The reviewed catalog is ready for deployment. The canonical source now contains
eight categories, 49 recipes, and 184 explicit variants. Production seeding writes
structured instructions directly, while the legacy parser remains available only
for existing parsed content.

All made-to-order drinks have paired `With ice` and `No ice` variants. No-ice
instructions include chilling cubes, the required liquid-sugar adjustment, and a
base-liquid top-up. The topping card is informational, is excluded from recipe
tests, and exposes identical calculated values in recipe-detail Inertia data and
the `read_recipes` assistant output.

## Requirement Evidence

- Full-catalog tests assert exactly 8 categories, 49 recipes, and 184 variants,
  with at least two explicit canonical instructions per variant.
- Catalog quality checks cover instruction metadata, normalized names and units,
  paired ice modes, no-ice sugar rules, duplicate steps, malformed wording, and
  prohibited legacy abbreviations or bracket modifiers.
- Focused regressions cover Lychee Tea, all three Black Tapioca batches, Ceylon and
  Oolong milk-tea blends, grouped flavor/base/size variants, Double Strawberry,
  70–80 °C whisking, exact Classic Matcha Latte and Coconut Cloud top-ups, and
  topping reductions including the zero floor.
- Controller and assistant tests verify the shared topping-adjustment payload.
- Seeder migration tests verify forced replacement, nulling of deleted recipe and
  variant references, preservation of historical snapshots, and
  `is_inferred = false` for every canonical instruction.
- Legacy conversion and immutable recipe-test snapshot behavior remain covered.

## Deployment Behavior

Migration `2026_09_02_000001_force_replace_reviewed_recipe_catalog.php` invokes
the forced catalog-replacement path. As explicitly authorized for this delivery,
it deletes current recipe categories, recipes, variants, and admin edits. Before
deletion it nulls historical attempt foreign keys; stored attempt snapshots remain
unchanged.

No separate production seed command is required. The standard deployment
`php artisan migrate --force` path applies the replacement once.

## Fresh Verification

- `make fix` — passed.
- `make check` — passed:
    - PHPStan max analyzed 609 files without errors.
    - Prettier and Pint checks passed.
    - Composer and npm security audits passed.
    - TypeScript type-check and Vite production build passed.
    - 77 Vitest tests passed.
    - 888 PHP tests passed with 51,689 assertions; one unrelated test was skipped.
- `./node_modules/.bin/playwright test tests/e2e/recipes.spec.ts` — all three
  Chromium scenarios passed: admin browsing/results, mobile worker failure flow,
  and an exact 100% pass flow using randomly selected variants.
- The randomized exact-answer scenario was repeated three additional times and
  passed every run.
- `git diff --check` — passed.

## Readiness

- Verdict: ready for deployment.
- Blockers: none.
- Known risk: the catalog replacement is intentionally destructive to editable
  recipe data. Historical test evidence is protected by immutable snapshots and
  verified foreign-key nulling.
- Runtime emitted the existing Node `module.register()` deprecation warning during
  build and Playwright startup; it did not affect validation and is outside this
  recipe change.
