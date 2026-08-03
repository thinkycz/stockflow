# Volitelné hodnocení docházky – implementační plán

## Fáze 1: Persistované nastavení brigádníka

- T1.1: Přidat boolean sloupec s defaultem `true`, modelový kontrakt a factory.
- T1.2: TDD rozšířit create/edit/index controllery, validaci a formuláře.

## Fáze 2: Doménové vynucení a datové kontrakty

- T2.1: TDD přidat stav `disabled` do společné ratingové služby a zamezit
  výpočtu ratingu vypnutých brigádníků.
- T2.2: Propagovat explicitní stav a nullable metriky do docházkového přehledu,
  interních směn a veřejného kalendáře při zachování hodin a mzdy.

## Fáze 3: UI a lokalizace

- T3.1: Zobrazit stav v Brigádnících a checkbox ve formulářích.
- T3.2: Zobrazit `CircleOff` a skrýt ratingové metriky na všech konzumentech.
- T3.3: Synchronizovat CS/EN/SK překlady a dokumentaci architektury.

## Fáze 4: Ověření a closeout

- T4.1: Spustit cílené backendové a frontendové testy.
- T4.2: Spustit formátování, `make check` a relevantní runtime/E2E ověření.
- T4.3: Aktualizovat matici, tracker a verifikační zprávu.
