# Denní provozní Slack souhrn – phase tracker

## Status

- Current phase: Phase 4
- Overall status: verified; external Slack smoke deferred
- Last updated: 2026-08-03

## Phase 1: Transakční journal

- Goal: Vytvořit úplný zdroj provozních milníků nezávislý na Slack konfiguraci.
- Status: verified
- Tasks:
    - [x] Persistence, model a factory.
    - [x] Napojení obou dispatch API.
    - [x] Rollback, transfer a no-config testy.
- Blockers: žádné.

## Phase 2: Digest doména a scheduler

- Goal: Sestavit, uložit a spolehlivě odeslat jeden denní souhrn.
- Status: verified
- Tasks:
    - [x] Digest persistence a vyčerpávající builder.
    - [x] Recovery a prune joby.
    - [x] Queued notification a retry lifecycle.
- Blockers: žádné.

## Phase 3: Admin archiv

- Goal: Zpřístupnit stav, detail a bezpečný retry hlavnímu adminovi.
- Status: verified
- Tasks:
    - [x] Index, detail a retry routy.
    - [x] Inertia UI a odkaz z Nastavení.
    - [x] CS/SK/EN překlady.
- Blockers: žádné.

## Phase 4: Dokumentace a verifikace

- Goal: Uzavřít ADR, traceability a úplné kontroly.
- Status: verified
- Tasks:
    - [x] ADR.
    - [x] Verification záznam.
    - [x] Cílené testy a lokální browser smoke.
    - [x] `make check`.
- Blockers: žádné pro code review; externí Slack smoke vyžaduje reálný bot token a kanál.

## Decisions

- Kanonické termíny jsou „denní provozní souhrn“ a „provozní journal“.
- Journal je transakční součást business commitu; Slack transport zůstává izolovaný.
- Digest je textová agregace, nikoliv event tabulka.
- Catch-up posílá nejvýše jeden zmeškaný den za hodinu.

## Deferred

- Reálné doručení do externího Slack workspace je provozní smoke test.

## Next

- Po nasazení provést kontrolovaný smoke v reálném Slack workspace.
