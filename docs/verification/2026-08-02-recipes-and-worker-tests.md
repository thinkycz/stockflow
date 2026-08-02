# Ověření receptů a testování brigádníků

## Výsledek

Implementace odpovídá schválenému plánu. Katalog obsahuje 8 kategorií a 49
receptů z dodaného PDF, inicializuje se pro firmu právě jednou a následné
požadavky nepřepisují adminovy změny. Admin spravuje strukturu a výsledky;
omezený účet vidí jen aktivní obsah a může zakládat neomezené pokusy pro
vybraného brigádníka.

Pokus náhodně vybere variantu, klientovi poskytne pouze promíchané neprůhledné
tokeny a server vyhodnotí procento přesných pozic. Splnění vyžaduje 100 %.
Snapshot receptu, varianty, kroků, brigádníka a auditního účtu zůstává zachován
po editaci, archivaci i odstranění brigádníka nebo omezeného účtu.

## Čerstvé důkazy

- `make check` — PHPStan bez chyb, Prettier a Pint prošly, Composer/NPM audity
  bez nálezů, type-check a produkční build prošly, 14 Vitest souborů / 44 testů
  a 655 Pest testů / 17 072 assertions prošlo.
- `npm run e2e -- tests/e2e/recipes.spec.ts` — 2 Chromium scénáře prošly nad
  čerstvou migrací a E2E seedem. Adminský scénář ověřil navigaci a výsledky;
  mobilní omezený účet ověřil read-only detail, výběr brigádníka, změnu pořadí,
  odeslání a zobrazení správného pořadí.
- Playwright CLI runtime kontrola otevřela `/recipes` jako omezený účet a
  pořídila full-page snapshot katalogu bez administračních prvků.
- Cílené service a controller testy pokryly jednorázový import, řazení kategorií,
  receptů, variant a kroků, archivaci/obnovu, tenantovou izolaci, vlastnictví
  pokusu, neúplné tokeny, částečné skóre, přesné splnění a stabilitu snapshotu.

## Release readiness

- Verdikt: připraveno k nasazení.
- Blokátory: žádné.
- Nasazení vyžaduje běžné spuštění Laravel migrací; katalog se naplní při prvním
  otevření sekce Recepty hlavním adminem nebo jeho omezeným účtem.
