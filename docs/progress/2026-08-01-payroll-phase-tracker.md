# Výplatní pásky — phase tracker

## Status

- Current phase: Dokončeno
- Overall status: verified
- Last updated: 2026-08-01

## Fáze 1: Doména a výpočty

- Goal: Trvalý model a ověřené výpočty pásek.
- Status: completed
- Tasks:
    - [x] Migrace, enumy, modely a validity
    - [x] Servisní testy (RED)
    - [x] Výplatní servis a životní cyklus (GREEN)
- Blockers: žádné

## Fáze 2: Webové rozhraní

- Goal: Admin stránka, editace a tisk.
- Status: completed
- Tasks:
    - [x] Routy a controllery
    - [x] Inertia stránky, sidebar a překlady
    - [x] Controller a frontend testy
- Blockers: žádné

## Fáze 3: Finanční report

- Goal: Finance čerpají výsledky pásek se správným pořadím uzavření.
- Status: completed
- Tasks:
    - [x] Nahradit zdroj mzdových řádků
    - [x] Doplnit lifecycle guardy a regresní testy
- Blockers: žádné

## Fáze 4: Verifikace

- Goal: Čerstvé důkazy pro backend, frontend a uživatelský tok.
- Status: completed
- Tasks:
    - [x] `make fix` a `make check`
    - [x] E2E/runtime smoke a verifikační záznam
- Blockers: žádné

## Decisions

- Základ vychází z plánovaných směn, docházka je pouze kontrolní.
- Úpravy jsou položkové; záporná výplata je zakázaná.
- Neúplná docházka varuje, ale neblokuje uzavření.
- Finanční mzdové overrides zůstávají dostupné.

## Deferred

- Žádné položky.

## Verification

- Viz `docs/verification/2026-08-01-payroll.md`.

## Rozšíření: ruční mzdový základ a jednoduchý tisk

- Status: completed
- Tasks:
    - [x] Měsíční override hodin a hodinové sazby s možností resetu
    - [x] Zamknutí override při uzavření a zahrnutí do snapshotu
    - [x] Detailní a zjednodušená varianta individuálního i hromadného tisku
    - [x] Regresní testy a aktualizovaný verifikační záznam
- Blockers: žádné

## Next

- Nasadit novou databázovou migraci v cílovém prostředí.
