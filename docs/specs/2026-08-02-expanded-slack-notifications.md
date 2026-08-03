# Rozšířené Slack notifikace

## Zdroj pravdy

- Uživatelem schválený plán z 2. 8. 2026.
- Původní kontrakt v `docs/specs/2026-07-22-store-slack-notifications.md`.
- Za nové se považují funkce přidané po commitu `65f9053`.

## Požadavky

- Zachovat queued, post-commit a failure-isolated doručování přes jeden Slack bot token.
- Přidat volitelný firemní Slack kanál spravovaný hlavním administrátorem v Nastavení.
- Store události posílat jen do příslušného store kanálu; firemní události jen do firemního kanálu.
- Notifikovat posouzení odchylky docházky, agregované checklistové milníky, uzavření a reopen mzdového a finančního reportu, výsledky receptových testů a lifecycle dárkových poukazů.
- Neodesílat důvody korekcí, odpovědi testů, kódy poukazů ani item-level payloady.
- Nástěnka, konfigurační CRUD, průběžné finanční a mzdové editace, drafty, autosave a odvozené read-only funkce zůstávají bez Slack zpráv.

## Události

- Docházka: odchylka schválena nebo zamítnuta.
- Checklist: směna dokončena nebo znovu otevřena; den omluven nebo omluva zrušena.
- Výplatní report: uzavřen nebo znovu otevřen.
- Finanční report: uzavřen nebo znovu otevřen.
- Receptový test: úspěšně nebo neúspěšně odevzdán; jedna zpráva za tříreceptové sezení.
- Poukazy: batch vydán, voucher uplatněn, voided nebo bylo vráceno uplatnění.

## Bezpečnost a routing

- Firemní Slack kanál je nullable, oříznutý a omezený na 100 znaků.
- Firemní konfiguraci mění pouze hlavní administrátor.
- Batch vydání se agreguje do jedné zprávy.
- Reversal voucheru používá prodejnu zachycenou před vymazáním redemption údajů.
- Chybějící kanál nebo bot token znamená tichý no-op.
