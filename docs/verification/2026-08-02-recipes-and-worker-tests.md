# Ověření receptů a testování brigádníků

## Výsledek

Implementace odpovídá schválenému plánu i následnému požadavku na deploy reset.
`/recipes` je prostý seznam kategorií a názvů drinků, správu kategorií má admin
na `/recipe-categories` a detail načítá jen jednu zvolenou variantu. Varianta se
zobrazuje i edituje jako jediná kompaktní výrobní sekvence bez zdrojového znění.

Pokus náhodně vybere variantu, promíchá všechny její instrukce a server vyhodnotí
procento přesných pozic; splnění vyžaduje 100 %. Nový snapshot obsahuje celou
sekvenci a její metadata, staré snapshoty se čtou v původním formátu.

Korektivní migrace `2026_08_02_000006_force_replace_recipe_catalog.php` sama
odstraní starých 8 kategorií a 49 receptů včetně adminových změn a vytvoří čistý
katalog v nové struktuře. Není závislá na `db:seed` a ignoruje i marker z
předchozího částečného deploye. Historické pokusy se před resetem odpojí od
starých ID, jejich snapshoty zůstanou beze změny a adminský přehled je páruje s
novým receptem podle snapshotu názvu.

## Čerstvé důkazy

- `make check` — PHPStan bez chyb, Prettier a Pint prošly, Composer/NPM audity
  bez nálezů, type-check a produkční build prošly, 14 Vitest souborů / 44 testů
  a 664 Pest testů / 17 441 assertions prošlo.
- `npm run e2e -- tests/e2e/recipes.spec.ts` — 2 Chromium scénáře prošly nad
  čerstvou migrací a E2E seedem. Adminský scénář ověřil navigaci a výsledky;
  mobilní omezený účet ověřil read-only detail, výběr brigádníka, změnu pořadí,
  odeslání a zobrazení správného pořadí.
- Playwright runtime kontrola ověřila kompaktní adminský index a detail Classic
  Matcha Latte s přesnou osmibodovou sekvencí.
- Vizuální artefakty: `output/playwright/recipes-compact-admin.png` a
  `output/playwright/recipe-classic-compact.png`.
- Cílené service a controller testy pokryly jednorázový import, rozdělení
  `100g milk + 20g sugar - stir`, fallbacky `half`/`a few`/`1,5`, ikonový
  override, řazení kategorií, receptů a instrukcí, archivaci/obnovu, tenantovou
  izolaci, vlastnictví pokusu, neúplné tokeny, částečné skóre, přesné splnění,
  stabilitu snapshotu a vynucený kompletní reset všech 8 kategorií, 49 receptů a
  všech variant i při již nastaveném starém markeru.

## Release readiness

- Verdikt: připraveno k nasazení.
- Blokátory: žádné.
- Nasazení vyžaduje pouze standardní `php artisan migrate --force`; nová `000006`
  datová migrace provede kompletní reset automaticky.
