# Docházka brigádníků – phase tracker

## Status

- Current phase: Phase 4 – Closeout
- Overall status: implemented and verified; release blocked by dependency audit
- Last updated: 2026-07-21

## Phase 1: Doména a persistence

- Status: verified
- Tasks:
    - [x] Migrace, modely, továrny a enum
    - [x] Stavový automat a párování směn
    - [x] Obsazenost, stale stav a výpočty
    - [x] Modelové a servisní testy
- Blockers: none

## Phase 2: Webové rozhraní a výkazy

- Status: verified
- Tasks:
    - [x] Index a stavové akce
    - [x] Auditované adminské opravy
    - [x] Report a tisk
    - [x] Controller testy
- Blockers: none

## Phase 3: Frontend

- Status: verified
- Tasks:
    - [x] Navigace a provozní UI
    - [x] Report, korekce a tiskové UI
    - [x] Překlady, type-check a build
- Blockers: none

## Phase 4: Closeout

- Status: verified with external release blocker
- Tasks:
    - [x] Dokumentace
    - [x] Plná validace a release readiness
- Blockers: `composer audit` reports four medium advisories for locked `guzzlehttp/guzzle` below 7.15.1

## Decisions

- Sdílený účet vybírá brigádníka; osobní účet není vyžadován.
- Admin smí používat běžné stavové akce.
- Výkazy a sazby jsou admin-only.
- Export je tisková HTML stránka, nikoli PDF nebo CSV.

## Deferred

- Notifikace, PDF a CSV export.

## Verification

- `make fix`: passed
- PHPStan, Prettier and Pint: passed through `make check`
- Frontend type-check and production build: passed
- Vitest: 14 passed
- Pest: 510 passed after the final attendance boundary tests
- `make check`: blocked only at `composer audit` by pre-existing Guzzle advisories published 2026-07-20

## Next

- Upgrade `guzzlehttp/guzzle` to a fixed compatible version (at least 7.15.1) and rerun `make check` before release.
