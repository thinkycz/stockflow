# Recepty a testování brigádníků – implementační plán

## Fáze 1: Doména a katalog

- Přidat migrace, modely, factory, validity a transakční služby.
- Přidat idempotentní firemní inicializaci a přesný strukturovaný katalog z PDF,
  včetně parseru surovin a akčních kroků.
- Test-first pokrýt inicializaci, snapshot, promíchání a vyhodnocení.

## Fáze 2: HTTP a oprávnění

- Přidat společné čtecí routy, admin CRUD/archivaci a omezený testovací workflow.
- Přidat adminský přehled brigádník → recepty → historie pokusů.
- Pokrýt role, tenancy, cizí pokusy a archivaci feature testy.

## Fáze 3: Inertia UI

- Přidat katalog seskupený podle kategorií s inline variantami, ikonami,
  strukturovanými surovinami, detail/editor, testovací řazení a výsledkový přehled.
- Doplnit dotykové drag-and-drop, tlačítka nahoru/dolů, responsivitu a CS/SK/EN překlady.
- Umístit Recepty za Checklisty adminovi a za Docházku omezenému účtu.

## Fáze 4: Ověření a handoff

- Doplnit E2E a navigační kontrakty.
- Spustit cílené testy, type-check, build, `make fix` a `make check`.
- Aktualizovat traceability, tracker a verification report podle čerstvých výsledků.

## Fáze 5: Strukturovaný katalog (follow-up)

- Rozšířit varianty o suroviny a kroky s ikonovou taxonomií a zachovat kompatibilitu
  historických pokusů.
- Převést importované řádky deterministickými pravidly bez druhého spuštění importu.
- Zobrazit všechny recepty inline a editoru zpřístupnit pořadí, fallbacky a override ikon.

## Fáze 6: Kompaktní katalog a jednotná výrobní sekvence

- Přidat kanonické `recipe_instructions` vedle legacy surovin a kroků a převést
  každou firmu právě jednou bez přepsání jejích aktuálních hodnot.
- Změnit testy a nové snapshoty na celou seřazenou výrobní sekvenci; staré
  snapshoty dál číst v původním formátu.
- Zjednodušit `/recipes` na seznam kategorií a drinků, přesunout správu kategorií
  na `/recipe-categories` a detail zobrazit jako jednu kompaktní variantu.
- Nahradit editor surovin/postupu jedním editorem instrukcí a odstranit zdrojové
  znění ze všech uživatelských obrazovek.
- Přidat idempotentní deploy seeder, který jednou odstraní starý katalog, odpojí
  historické pokusy a naplní čistý katalog v nové instrukční struktuře.
