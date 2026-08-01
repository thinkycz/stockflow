# Checklisty provozovny – Phase Tracker

## Status

- Current phase: 4 – Ověření dokončeno
- Overall status: completed
- Last updated: 2026-08-01

## Phase 1: Doména a snapshoty

- Status: completed
- [x] Selhávající doménové testy
- [x] Migrace, enumy, modely a factory
- [x] Výchozí katalog a inicializace retail provozoven
- [x] Idempotentní denní snapshoty, stavy a audit

## Phase 2: HTTP a oprávnění

- Status: completed
- [x] Admin šablony a historie
- [x] Dashboard payload a toggle
- [x] Omluvení dne, scheduler a Store create integrace

## Phase 3: UI

- Status: completed
- [x] Navigace a admin stránka
- [x] Dashboard kartičky
- [x] CS/SK/EN překlady

## Phase 4: Ověření

- Status: completed
- [x] Feature a E2E testy
- [x] Type-check, build, fix a check
- [x] Verification evidence

## Blockers

- Žádné.

## Decisions

- Snapshoty platí od začátku dne; změna šablony se projeví následující den.
- Auditní účet a brigádník jsou dvě oddělené identity.
- Historická změna je povolena pouze pro omluvení dne.
