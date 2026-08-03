# Denní provozní Slack souhrn – implementační plán

## Phase 1: Transakční journal

- Přidat persistence model, factory a přesné gettery.
- Ukládat jeden scalar snapshot v `OperationalActivityService` před routingem.
- Ověřit rollback, chybějící konfiguraci a transfer se dvěma kontexty.

## Phase 2: Digest doména a scheduler

- Přidat digest model, status enum, builder a neměnný strukturovaný snapshot.
- Přidat idempotentní creation/recovery job a 90denní prune job.
- Přidat queued Slack renderer se stavovými přechody a retry.

## Phase 3: Admin archiv

- Přidat admin-only index, detail a retry endpoint.
- Přidat textové Inertia obrazovky a odkaz z firemní Slack karty.
- Synchronizovat CS/SK/EN překlady.

## Phase 4: Dokumentace a verifikace

- Zapsat ADR pro transakční provozní journal.
- Ověřit cílené doménové, job, notification, controller a UI testy.
- Spustit `make fix`, frontend kontroly a celý `make check`.
