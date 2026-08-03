# Volitelné hodnocení docházky – phase tracker

## Stav

- Aktuální fáze: dokončeno
- Celkový stav: verified
- Poslední aktualizace: 2026-08-03

## Fáze 1: Persistované nastavení brigádníka

- Cíl: bezpečně uložit a spravovat opt-out.
- Stav: done
- Úkoly:
    - [x] T1.1 Databáze, model a factory
    - [x] T1.2 Validace, controllery a formuláře
- Blokátory: žádné

## Fáze 2: Doménové vynucení a kontrakty

- Cíl: odstranit rating vypnutých brigádníků ze všech serverových výstupů.
- Stav: done
- Úkoly:
    - [x] T2.1 Ratingová služba
    - [x] T2.2 Docházka, interní a veřejné směny
- Blokátory: žádné

## Fáze 3: UI a lokalizace

- Cíl: zobrazit jednoznačný a přístupný vypnutý stav.
- Stav: done
- Úkoly:
    - [x] T3.1 Brigádníci
    - [x] T3.2 Docházka a směny
    - [x] T3.3 Překlady a architektura
- Blokátory: žádné

## Fáze 4: Ověření a closeout

- Cíl: doložit shodu se specifikací a projektovými branami.
- Stav: verified
- Úkoly:
    - [x] T4.1 Cílené testy
    - [x] T4.2 Plná kontrolní brána a runtime smoke
    - [x] T4.3 Verifikační dokumentace
- Blokátory: žádné

## Rozhodnutí

- Vypíná se pouze hodnocení, nikoli evidence docházky.
- Stav platí nad celou historií a je vratný.
- Hard enforcement je ve společné ratingové službě.
- Existující změny kolem denního Slack digestu jsou mimo tento slice a nebudou
  upravovány ani připisovány této práci.

## Deferred

- Nic.

## Další krok

- Připraveno k review a nasazení včetně databázové migrace.
