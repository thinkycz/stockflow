# Virtuální nástěnka – phase tracker

## Stav

- Current phase: Fáze 4 – Životní cyklus a uzavření
- Overall status: verified
- Last updated: 2026-07-27

## Fáze 1: Doména a bezpečný zápis

- Goal: bezpečný a testovatelný model kartičky.
- Status: verified
- Tasks:
    - [x] migrace, enumy, model a factory
    - [x] validity a sanitizer
    - [x] CRUD služba, soubory a optimistický zámek
    - [x] cílené testy
- Blockers: none

## Fáze 2: Webový kontrakt

- Goal: kompletní store-scoped HTTP a Inertia kontrakt.
- Status: verified
- Tasks:
    - [x] dashboard seznam, filtry a stránkování
    - [x] CRUD a privátní obrázek
    - [x] koš, obnovení a force-delete
    - [x] feature testy rolí a scope
- Blockers: none

## Fáze 3: Uživatelské rozhraní

- Goal: responzivní sticky-note UI s WYSIWYG editorem.
- Status: verified
- Tasks:
    - [x] Tiptap editor
    - [x] formulářový a detailový modal
    - [x] mřížka, filtry, hledání a stránkování
    - [x] jednotný nadpis, umístění v sekci Prodejna a centrální store pill
    - [x] frontendový build/type-check a E2E scénář
- Blockers: none

## Fáze 4: Životní cyklus a uzavření

- Goal: automatický úklid, dokumentace a ověřená dodávka.
- Status: verified
- Tasks:
    - [x] prune command a scheduler
    - [x] dokumentace
    - [x] plná verifikace
- Blockers: none

## Rozhodnutí

- Kartička patří jedné prodejně.
- Všichni uživatelé prodejny mohou upravovat a soft-delete všechny její karty.
- Koš je admin-only a má 30denní retenci.
- Expirovaná karta není smazaná karta.
- Bez realtime, verzovací historie a drag-and-drop.
- Nástěnka je plochá sekce bez eyebrow a vnořeného panelu; detailní adminská
  analytika patří na stránku Statistiky a omezený dashboard používá čtyři
  kompaktní rychlé akce.
- Nástěnka je první položkou sekce Prodejna. Store-scoped stránky sdílejí
  klasifikaci tras a `AppLayout` na nich zobrazuje informační pill aktivní
  prodejny.

## Deferred

- none

## Next

- Nasadit migraci a standardním release procesem předat změnu do review.
