# Ověření receptů a testování brigádníků

## Výsledek

Implementace odpovídá schválenému plánu i follow-upu pro přehlednější katalog.
Katalog obsahuje 8 kategorií a 49 receptů z dodaného PDF, inicializuje se pro
firmu právě jednou a následné požadavky nepřepisují adminovy změny. Index nyní
seskupuje recepty podle kategorií a zobrazuje všechny varianty inline; každá
varianta má oddělené řádky surovin s množstvím, fallbackem a ikonou a samostatný
číslovaný postup.

Pokus náhodně vybere variantu, suroviny zobrazí jako pevný seznam, klientovi
poskytne pouze promíchané neprůhledné tokeny postupových kroků a server vyhodnotí
procento přesných pozic. Splnění vyžaduje 100 %. Nový snapshot obsahuje celou
variantu včetně množství a ikon; staré snapshoty se nepřepočítávají. Historie
zůstává zachována po editaci, archivaci i odstranění brigádníka nebo omezeného
účtu.

## Čerstvé důkazy

- `make check` — PHPStan bez chyb, Prettier a Pint prošly, Composer/NPM audity
  bez nálezů, type-check a produkční build prošly, 14 Vitest souborů / 44 testů
  a 660 Pest testů / 17 252 assertions prošlo.
- `npm run e2e -- tests/e2e/recipes.spec.ts` — 2 Chromium scénáře prošly nad
  čerstvou migrací a E2E seedem. Adminský scénář ověřil navigaci a výsledky;
  mobilní omezený účet ověřil read-only detail, výběr brigádníka, změnu pořadí,
  odeslání a zobrazení správného pořadí.
- Playwright E2E runtime kontrola otevřela `/recipes` jako omezený účet a
  ověřila inline suroviny a postup; E2E navíc prošlo nad aktualizovaným
  mobilním selektorem katalogové karty.
- Vizuální artefakt katalogu: `output/playwright/recipes-inline-limited.png`.
- Cílené service a controller testy pokryly jednorázový import, rozdělení
  `100g milk + 20g sugar - stir`, fallbacky `half`/`a few`/`1,5`, ikonový
  override, řazení kategorií,
  receptů, variant a kroků, archivaci/obnovu, tenantovou izolaci, vlastnictví
  pokusu, neúplné tokeny, částečné skóre, přesné splnění a stabilitu snapshotu.

## Release readiness

- Verdikt: připraveno k nasazení.
- Blokátory: žádné.
- Nasazení vyžaduje běžné spuštění Laravel migrací; katalog se naplní při prvním
  otevření sekce Recepty hlavním adminem nebo jeho omezeným účtem.
