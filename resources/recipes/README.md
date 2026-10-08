# Editing recipes

The application and assistant read this directory directly. Each recipe is one
Markdown file in its category folder. There is no recipe database, editor, or staff
testing workflow.

The library contains 54 recipes and 189 explicitly authored variants. Recipe text
uses English; interface controls are translated into English, Czech, and Slovak.

## Change an existing recipe

1. Open its Markdown file, for example
   [Classic Matcha](hot-drinks/classic-matcha.md).
2. Edit the ingredients, method, equipment, summary, or notes.
3. Keep the ingredient quantities and method quantities consistent. Do not convert
   weights to volumes or guess missing amounts or preparation times.
4. Run `make fix` and `make check`. Catalog validation runs as part of the checks.

## Document structure

The first fenced `json` block contains display metadata. It is followed by an H1
recipe title, a short description, an `## Equipment` bullet list, and one H2 section
per variant. Optional `## Notes` and `## Related preparations` sections come last.

Metadata contains `position`, `aliases`, `tags`, and a `variants` array. Each variant
has a stable `key`, a `name` matching its H2 heading, `selectors`, an ordered `actions`
array, and a `timers` object. Copy an existing recipe as the starting point.

- `position` sets the recipe order within its category.
- `aliases` supplies alternate names for quick lookup, especially combined drinks.
- `tags` supplies search keywords and the decorative drink illustration. Use `hot`,
  `iced`, or `batch`, plus relevant ingredients such as `matcha`, `tea`, or `taro`.
- Selector dimensions are `size`, `ice`, `flavour`, `batch`, and `temperature`. Ice
  values are `with-ice` and `no-ice`. Only authored combinations are selectable.
- Each method step has one corresponding action, in the same order. Available
  actions are `add`, `mix`, `stir`, `whisk`, `whip`, `boil`, `heat`, `steam`, `steep`,
  `ice`, `shake`, `pour`, `smash`, `cook`, `cover`, `timer`, `cool`, `garnish`, `serve`,
  `wash`, and `other`.
- `timers` maps a one-based method step to seconds, for example `{"3": 600}` for a
  known ten-minute step. Leave it `{}` when the source gives no exact duration.
  Ranges, visual endpoints, and "a few minutes" must not become fixed timers.

Each variant contains `### Ingredients` and `### Method`. The ingredient table uses
exactly `Component | Ingredient | Amount | Unit`. Components visually group the
base, matcha, cloud, or finishing ingredients. Use decimal points in numeric
amounts. Preserve exact text such as `2–3` or `as needed` when no single number is
specified. Units are `g`, `kg`, `ml`, `L`, `pieces`, `scoops`, or `—` for no unit.
`scoops` always means standard scoops. Dry tea is named tea leaves; a brewed base is
named brewed tea. Cream in the strawberry cloud means whipping cream.

List every ingredient used by the method, including ingredients added to a volume
line. Use text amounts for these additions rather than inventing a measured volume:

- Iced drinks: `Ice | ice cubes | full serving cup | —`. For shaker drinks, measure
  the ice in the serving cup and transfer it to the shaker.
- Taro bases: `Milk to volume | milk | to 300 ml total mixture | —` (or coconut milk
  and the authored 400 ml size). This is the total mixture volume, not the amount
  of milk to add.
- Final additions: `Top-up | milk | to serving line | —`, using the correct milk
  or brewed tea. Assemble the drink base first, then top up in the serving cup.
  Leave room for any cloud or finishing tea layer and add that layer afterward.
- Tea preparations: `Batch dilution | ice cubes | to 3.5 L total batch | —`, using
  the authored final batch size. Do not calculate the amount of ice by subtracting
  starting water from the final volume; powders and other ingredients add volume.
- Tapioca: keep cooking water, sauce ingredients, and rinsing water in their own
  components. Drain and rinse before adding the measured sauce ingredients.

Number each method step sequentially, with a short bold title and a complete
instruction on the same line:

```markdown
1. **Measure milk.** Add 200 g milk to the milk jug.
2. **Steam the milk.** Steam the milk in the milk jug.
```

An optional `### Tips` section below the method supports ordinary Markdown. Recipe
notes and tips are rendered with raw HTML and unsafe links disabled. Related
preparations use bullet links such as:

```markdown
- [Ceylon milk tea preparation](/recipes/preparations/ceylon-milk-tea-preparation)
```

## Add a recipe or category

Add the document to an existing category folder and give it the next position.
For a category, add its key, display name, and position to `catalog.json`, then
create a matching folder with at least one recipe. Keys and filenames use lowercase
words separated by hyphens. The folder and filename form the permanent URL:
`/recipes/hot-drinks/classic-matcha`. Keep them stable when changing display names.
Duplicate drink names across categories remain separate recipes.

## Operational rules

No-ice recipes still use the specified 2–3 chilling cubes, adjusted sweetener
amounts, and top-ups. Both ice variants list their ice amounts explicitly. Follow
the selected variant rather than subtracting ice from another variant. Topping
guidance is shown separately: liquid sugar and syrup in
millilitres are reduced by 5 ml for two toppings or 10 ml for three, with a minimum
of zero. It does not alter the authored method or apply to gram-based hot drinks.

Preparation checklists, progress, and timers are temporary page state. They are
reset when changing variants or leaving the recipe. Prices are omitted.

Hojicha Cloud follows the matcha-cloud method: whip the powder directly into the
cream and milk. Cream Cheese portions use one-fifth of the batch ingredients.
Oolong Milk Tea's smaller batch and the larger tapioca sauces use the confirmed
proportions recorded in [the logical recipe audit](../../docs/verification/2026-10-09-recipe-logic-audit.md).
Butterfly Tea uses a deep, dark blue visual brewing endpoint, without a guessed
fixed timer.

The retired database catalog and staff-test results are deleted by
`2026_10_08_000001_remove_database_recipes.php`. Historical creation migrations stay
runnable for fresh installations, but their catalog-seeding compatibility service
does nothing. The new migration runs through the normal deployment workflow.
