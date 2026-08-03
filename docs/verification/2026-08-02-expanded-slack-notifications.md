# Rozšířené Slack notifikace – verification

## Výsledek

Schválený rozsah je implementovaný a připravený k předání. Všechny nové provozní milníky mají otestovaný store nebo firemní routing, firemní kanál lze spravovat v Nastavení a vyloučené průběžné toky zůstávají bez zpráv. Nezůstává žádný release blocker.

## Automatizované důkazy

- Cílený Pest běh: 90 testů a 411 assertions prošlo pro nastavení, Slack infrastrukturu, docházku, checklisty, payroll, finance, receptové testy, poukazy a tiché regresní scénáře.
- `make fix`: prošlo; Prettier a Pint upravily a ověřily dotčené soubory.
- `make check`: prošlo po poslední změně, včetně PHPStan na maximální úrovni, Prettier/Pint kontrol, Composer a npm auditů, platform requirements, TypeScript type-checku a produkčního Vite buildu.
- Frontend unit testy: 46 testů v 15 souborech prošlo.
- Celý Pest suite: 694 testů a 18 942 assertions prošlo.
- Překladové parity testy potvrdily stejné klíče v CS/SK/EN pro frontend i backend.

## Ověřené chování

- Store kanál: posouzení odchylky, agregované checklistové přechody, omluvení, payroll a finanční close/reopen, voucher redemption a reversal.
- Firemní kanál: finální receptové sezení, legacy pokus bez sezení, voucher batch issue a void.
- Checklist dokončení a reopen vzniknou pouze při přechodu agregovaného stavu; item-level změny jsou tiché.
- Receptové sezení vytváří jednu zprávu místo tří child zpráv.
- Voucher reversal používá prodejnu zachycenou před vynulováním redemption dat; payloady neobsahují voucher kód ani korekční důvod.
- Chybějící bot token, store kanál nebo firemní kanál je no-op; event je post-commit a notification queued.
- Nástěnka, katalogový CRUD, payroll adjustments a průběžné finanční řádky mají regresně potvrzené ticho.

## Reziduální ověření

Reálné doručení do externího Slack workspace nebylo provedeno, protože vyžaduje produkční bot token, existující kanály a členství bota. Konstrukce payloadu, routing, no-op konfigurace, post-commit dispatch a queue izolace jsou pokryté automatizovanými testy.
