# Assistant recipe grounding

## Symptom

In the production conversation “Příprava oolong milk tea”, the assistant answered “jak se dela oolong milk tea” with a generic home recipe instead of the saved Stockflow recipe. After “podle naseho receptu”, it inspected recipe-category metadata, incorrectly scoped recipes to the active store, and claimed that no matching recipe existed.

## Evidence and root cause

- The saved catalog contains both `CEYLON/JASMINE/OOLONG MILK TEA` and `OOLONG MILK TEA (3.5l) (steep)` with structured variants and ordered instructions.
- The agent instructions required tools only when a question “depends on live application data”. A named recipe phrased as a general preparation question could therefore be answered from model knowledge.
- `read_recipes` returned full instructions only from `detail`, which required a recipe ID obtained through an earlier call. Its normal list was intentionally shallow.
- The categories dataset did not state that it could not establish recipe existence, so a model could make an invalid negative claim from a complete category response.
- Recipe data is company-wide, but the model applied the active-store context to it.
- Category records referenced the nonexistent `recipes.categories.index` route, causing category list/detail calls to return an `INVALID_REQUEST` envelope.

## Fix

- Named Stockflow entities and “our/we” questions now require the matching read tool before an answer; general knowledge may not replace matching saved data.
- The agent explicitly treats recipes as company-wide and forbids negative claims across sibling datasets.
- `read_recipes` now has a closed `lookup` schema branch. It accepts a recipe name or natural question, removes common Czech, Slovak, and English framing words, performs a tenant-scoped lookup, and returns complete variants and instructions in one bounded result.
- Recipe and category results declare company scope. Category responses explicitly direct recipe-existence questions to the recipes lookup.
- The category URL now uses the real `recipe-categories.index` route.

## Regression coverage

- A natural Czech question resolves the saved Oolong Milk Tea recipe and all three ordered instructions.
- A matching recipe owned by another company is excluded.
- The lookup branch is closed, typed, and bounded.
- Category results cannot be interpreted as proof that a recipe is absent.
- Agent instructions retain the named-entity grounding, company scope, and dataset-completeness rules.
- The opt-in live OpenRouter smoke now asks the original kind of recipe question without naming a tool and requires MiniMax to select `read_recipes`.
