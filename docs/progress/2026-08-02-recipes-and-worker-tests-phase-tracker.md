# Recepty a testování brigádníků – phase tracker

## Stav

- Aktuální fáze: Fáze 7 – dokončeno
- Celkový stav: done
- Poslední aktualizace: 2026-08-02
- Výchozí worktree: změny follow-upu připravené k předání, větev `main`

## Fáze 1: Doména a katalog

- Stav: done
- [x] Migrační schéma a modely
- [x] Validity a transakční služby
- [x] Jednorázový import PDF
- [x] Service a import testy
- Blokátory: žádné

## Fáze 2: HTTP a oprávnění

- Stav: done
- [x] Routy a controllery
- [x] Admin CRUD a výsledky
- [x] Role/tenancy feature testy
- Blokátory: žádné

## Fáze 3: Inertia UI

- Stav: done
- [x] Katalog a editor
- [x] Testovací workflow a výsledky
- [x] Navigace a překlady
- Blokátory: žádné

## Fáze 4: Ověření

- Stav: done
- [x] E2E a kontraktní testy
- [x] Plné repo kontroly
- [x] Verification report a readiness verdict
- Blokátory: žádné

## Fáze 5: Strukturovaný katalog

- Stav: done
- [x] `recipe_ingredients`, akce kroků a snapshot celé varianty
- [x] Deterministický parser importu včetně fallback množství a ikon
- [x] Inline katalog podle kategorií a sdílené ikonové variantní bloky
- [x] Admin editor surovin, pořadí, ikon a samostatných kroků
- [x] Test pouze nad postupovými tokeny, pevný seznam surovin
- [x] Parser, CRUD, snapshot a E2E testy
- Blokátory: žádné

## Rozhodnutí

- Omezený účet vybírá brigádníka bez dalšího ověření identity; audit ukládá aktéra.
- Nové sezení testuje tři různé náhodné recepty, z každého jednu náhodnou variantu.
- Úspěch vyžaduje přesné pořadí i všechna číselná množství `g`/`ml`; opakování je neomezené.
- Recepty jsou firemní a historické pokusy jsou snapshoty.
- Suroviny se zobrazují odděleně od testovatelných postupových kroků; akční
  ikony jsou odvozené parserem, ale admin je může změnit. Toto rozhodnutí
  nahrazuje Fáze 6: nové recepty používají jednu testovatelnou sekvenci.

## Fáze 6: Kompaktní katalog a jednotná výrobní sekvence

- Stav: done
- [x] Kanonické instrukce a idempotentní převod legacy dat
- [x] Nové snapshoty a test celé sekvence při zachování legacy historie
- [x] Prostý index a samostatná adminská stránka kategorií
- [x] Kompaktní detail s přepínačem variant a jednotný editor
- [x] CS/SK/EN, cílené testy, E2E a plné repo kontroly
- [x] Jednorázová datová migrace čistého katalogu se zachováním snapshotů
- Blokátory: žádné

## Fáze 7: Konzistentní katalog a test tří receptů

- Stav: done
- [x] Přístupný jednotný dropdown
- [x] Title-case názvy receptů při zachování uppercase kategorií a historie
- [x] Tři různé náhodné recepty v jednom sezení
- [x] Přesné odpovědi množství `g`/`ml` bez úniku správné hodnoty
- [x] Indexový start, sekvenční wizard a souhrnný výsledek
- [x] Admin výsledky, CS/SK/EN, neúspěšné i úspěšné E2E a plné ověření
- Blokátory: žádné
