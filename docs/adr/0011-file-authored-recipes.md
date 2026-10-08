# ADR 0011: File-authored recipe library

## Status

Accepted — 2026-10-08. Supersedes ADR 0005 and ADR 0006.

## Decision

Recipes are maintained in `resources/recipes`, with one Markdown document per
recipe and a JSON category manifest. The validated repository supplies the same
quantities, ordered methods, explicit variants, and preparation links to the
Inertia pages and the assistant. Recipe identities are category and recipe slugs.

Both administrator and permitted limited accounts have reference access. The
library offers quick autocomplete lookup, grouped visual cards, variant selectors,
and optional guided preparation with temporary checklists and known-duration
timers. English recipe documents are shared across translated interface controls.

Creation, editing, archiving, category management, staff tests, test history, and
assistant recipe writes are removed. A forward migration drops all eight recipe
and recipe-test tables plus the three user initialization columns. Existing
operational activity records remain readable, including their historical enum
values. No new recipe-test events are recorded.

## Consequences

The reviewed 49-recipe code catalog is migrated with all 184 variants and original
measurements. Five hot drinks add five variants; prices are omitted, grams are
preserved, scoops are standardized, and the new strawberry garnish uses a count.
Database-only customizations are intentionally retired in favor of the reviewed
catalog. There is no migration from arbitrary database content into files.

Changes to a recipe use the normal code review and deployment process. File
validation, measurement parity, route permissions, assistant pagination, and
browser workflows are covered by automated checks. The retirement migration is
destructive and has no automatic rollback; older deployments require restoring a
database backup.
