# Denní provozní Slack souhrn

## Zdroj pravdy

- Uživatelem schválený plán z 3. 8. 2026.
- Stávající Slack kontrakty v `docs/specs/2026-07-22-store-slack-notifications.md` a `docs/specs/2026-08-02-expanded-slack-notifications.md`.
- ADR 0002 pro jednu firmu a jednoho hlavního administrátora.

## Požadavky

- V 07:00 `Europe/Prague` odeslat do firemního Slack kanálu jeden český textový souhrn předchozího kalendářního dne.
- Zahrnout všechny provozní milníky z `OperationalActivityTypeEnum`, seskupené podle aktivních prodejen, skladu a celofiremních událostí.
- Ukládat milníky do transakčního provozního journalu nezávisle na dostupnosti Slack konfigurace.
- Zachovat privacy hranice okamžitých notifikací: bez důvodů, voucher kódů, odpovědí testů, item-level dat, draftů a CRUD.
- Udržet jednu Slack zprávu; při velkém objemu ponechat všechny počty a přesunout detail do archivu.
- Vytvářet prázdný heartbeat souhrn a doposílat zmeškané dny nejvýše jeden za hodinu.
- Uchovávat journal a digesty 90 dní bez historického backfillu před datem aktivace.
- Poskytnout admin-only archiv, detail a ruční retry pouze neúspěšného digestu.

## Doručovací kontrakt

- Digest má unikátní klíč `(company_user_id, digest_date)` a stavy `pending`, `queued`, `sent`, `failed`.
- Queued notification má pět pokusů s rostoucím backoffem a po výsledku aktualizuje stav digestu.
- Chybějící bot token nebo firemní kanál vytvoří `failed` záznam s bezpečnou chybou.
- Běžné souběžné běhy nevytvářejí duplicity; transportní duplicita po Slack timeoutu zůstává přijaté riziko.
