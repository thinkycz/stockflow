# Recipe Catalog Audit and Rewrite

## Status

- Current phase: complete
- Overall status: complete
- Last updated: 2026-09-02
- Pre-existing worktree changes: none

## Requirements

- [x] Replace the 49-recipe default catalog with explicit canonical instructions across eight categories.
- [x] Seed exactly 184 variants, including paired with-ice/no-ice drink variants and explicit grouped/batch variants.
- [x] Normalize English wording, units, ingredient names, temperatures, actions, targets, and vague source quantities.
- [x] Compute the 0–1/2/3 topping adjustments for liquid sugar and flavored syrups without adding them to recipe tests.
- [x] Expose topping guidance on recipe detail pages and through `read_recipes`.
- [x] Force-replace the deployed catalog while retaining immutable recipe-test snapshots.
- [x] Update focused, feature, E2E, and full-project verification.

## Phases

### Phase 1: Contract and canonical data

- [x] Add failing catalog-count and content-quality regressions.
- [x] Replace legacy source sentences with structured instruction rows.
- [x] Persist canonical instructions directly without parser inference.

### Phase 2: Adjustments and consumers

- [x] Add shared topping-adjustment calculation.
- [x] Render a prominent recipe-detail adjustment card.
- [x] Include the same guidance in assistant recipe reads.

### Phase 3: Deployment and verification

- [x] Add a new forced-replacement migration and snapshot-preservation coverage.
- [x] Update existing recipe tests and E2E expectations.
- [x] Run formatting, targeted tests, browser/runtime checks, and `make check`.

## Decisions

- Canonical categories and recipes remain at eight and 49; variants total 184.
- Existing production recipes, categories, and admin edits are intentionally discarded on deployment.
- Historical attempt snapshots remain immutable and recipe/variant references are nulled before deletion.
- No-ice variants remain testable; topping guidance remains informational and outside instruction ordering.
- Vague source amounts are clarified without inventing numeric quantities.

## Blockers

- None.

## Verification

- `make fix` completed successfully.
- `make check` completed successfully: PHPStan max, formatting, dependency audits,
  TypeScript, production build, 77 frontend tests, and 888 PHP tests with 51,689
  assertions all passed; one unrelated test remained skipped.
- `./node_modules/.bin/playwright test tests/e2e/recipes.spec.ts` passed all three
  admin, mobile worker, and exact-answer recipe scenarios.
- The randomized exact-answer scenario passed three additional consecutive runs.
- Detailed release evidence is recorded in
  `docs/verification/2026-09-02-recipe-catalog-audit-and-rewrite.md`.
