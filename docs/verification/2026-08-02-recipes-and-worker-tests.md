# Ověření receptů a testování brigádníků

## Výsledek

Implementace odpovídá schválenému plánu i následnému požadavku na deploy reset.
`/recipes` je prostý seznam kategorií a názvů drinků, správu kategorií má admin
na `/recipe-categories` a detail načítá jen jednu zvolenou variantu. Varianta se
zobrazuje i edituje jako jediná kompaktní výrobní sekvence bez zdrojového znění.

Nové testovací sezení náhodně vybere tři různé aktivní recepty a jednu použitelnou
variantu z každého. Průvodce uchová pořadí i rozepsaná množství mezi kroky a vše
odešle atomicky až na konci. Server hodnotí každou pozici a každé číselné množství
`g`/`ml` jako jeden check; splnění vyžaduje 100 % všech checků. Před odevzdáním
payload neobsahuje správnou hodnotu ani text, z něhož by ji šlo přečíst. Staré
single-recipe pokusy zůstávají dokončitelné a historické snapshoty se nepřepisují.

Řádkové menu katalogu používá sdílené stejně velké položky pro odkazy i akce,
zavírá se přes Escape a kliknutí mimo, podporuje šipky a destruktivně odlišuje
archivaci. Kategorie zůstávají uppercase, zatímco migrace, import i admin CRUD
ukládají recepty v title case; odpojené uppercase snapshoty se párují bez ohledu
na velikost písmen.

Korektivní migrace `2026_08_02_000006_force_replace_recipe_catalog.php` sama
odstraní starých 8 kategorií a 49 receptů včetně adminových změn a vytvoří čistý
katalog v nové struktuře. Není závislá na `db:seed` a ignoruje i marker z
předchozího částečného deploye. Historické pokusy se před resetem odpojí od
starých ID, jejich snapshoty zůstanou beze změny a adminský přehled je páruje s
novým receptem podle snapshotu názvu.

## Čerstvé důkazy

- `make check` — PHPStan max bez chyb, Prettier a Pint prošly, Composer/NPM audity
  bez nálezů, type-check a produkční build prošly a 670 Pest testů / 17 742
  assertions prošlo.
- `npm run test:unit` — 15 Vitest souborů / 46 testů prošlo včetně synchronních
  překladů, dropdown kontraktu a session wizard kontraktu.
- `npm run e2e -- tests/e2e/recipes.spec.ts` — 3 Chromium scénáře prošly nad
  čerstvou migrací a E2E seedem. Adminský scénář ověřil katalog a výsledky;
  mobilní omezený účet ověřil neúspěšné sezení a druhý scénář úspěšně seřadil
  všechny tři náhodné varianty, doplnil přesná množství a získal 100 %.
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
- Session testy navíc pokryly tři unikátní recepty, archivované a nedostatečné
  katalogy, výběr variant, čárku i tečku v desetinném čísle, přesnou shodu,
  chybějící množství, atomický rollback, vlastnictví, zákaz admin startu,
  payload bez odpovědi a kompatibilitu starých GET/PUT pokusů.

## Release readiness

- Verdikt: připraveno k nasazení.
- Blokátory: žádné.
- Nasazení vyžaduje standardní `php artisan migrate --force`; `000006` provede
  kompletní reset katalogu a `000007` přidá session schéma a normalizuje názvy.
