# Rozšířené Slack notifikace – phase tracker

## Status

- Current phase: Complete
- Overall status: verified
- Last updated: 2026-08-02

## Phase 1: Firemní kanál a infrastruktura

- Goal: Přidat centrální routing a zachovat kompatibilitu store notifikací.
- Status: verified
- Tasks:
    - [x] Persistence a admin Nastavení včetně UI.
    - [x] Nullable store kontext v notifikaci.
    - [x] Firemní dispatch a nové typy aktivit.
- Blockers: žádné.

## Phase 2: Store provozní milníky

- Goal: Napojit docházku, checklisty a měsíční reporty.
- Status: verified
- Tasks:
    - [x] Posouzení odchylky docházky.
    - [x] Agregované checklistové přechody.
    - [x] Payroll a financial lifecycle.
- Blockers: žádné.

## Phase 3: Firemní milníky

- Goal: Napojit receptové testy a voucher lifecycle.
- Status: verified
- Tasks:
    - [x] Test session a legacy result.
    - [x] Batch issue a void.
    - [x] Redemption a reversal routing.
- Blockers: žádné.

## Phase 4: Dokončení a verifikace

- Goal: Překlady, úplné kontroly a evidence.
- Status: verified
- Tasks:
    - [x] CS/SK/EN synchronizace.
    - [x] Cílené a úplné testy.
    - [x] Verification dokument.
- Blockers: žádné.

## Decisions

- Kanonický termín je „firemní Slack kanál“.
- Store a firemní kanály jsou nezávislé.
- Notifikují se provozní milníky, nikoliv každý zápis.

## Deferred

- Reálné doručení do Slacku vyžaduje externí bot token a pozvání bota do kanálů.

## Next

- Nasadit migraci a podle dostupnosti provést smoke test s reálným Slack workspace.
