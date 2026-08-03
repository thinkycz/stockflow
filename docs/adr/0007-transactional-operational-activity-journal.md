# ADR 0007: Transakční provozní journal

## Stav

Přijato 2026-08-03.

## Kontext

Okamžité provozní Slack notifikace byly založené na neperzistentních
událostech. Z těchto událostí proto nebylo možné sestavit zpětně denní
celofiremní souhrn, spolehlivě doposlat zmeškaný den ani zobrazit neměnný
archiv odeslaného obsahu.

## Rozhodnutí

- Každý dokončený provozní milník se před okamžitým Slack routingem
  uloží jako jeden záznam v `operational_activities`.
- Journal vzniká ve stejné databázové transakci jako business operace. Chyba
  jeho zápisu operaci rollbackne, protože journal je auditní součást commitu.
- Journal ukládá pouze bezpečný scalar snapshot, stabilní URL a snapshot
  dotčených lokalit. Důvody, voucher kódy, odpovědi testů, item-level data,
  drafty a katalogové CRUD operace se neukládají.
- Jeden transfer má jeden journal záznam s oběma perspektivami lokací.
- Denní builder vytváří neměnný snapshot v `operational_daily_digests`.
  Unikát `(company_user_id, digest_date)` je idempotency hranice scheduleru.
- Slack fronta a transport zůstávají oddělené `afterCommit`; jejich selhání
  nemění výsledek již potvrzené business operace.
- Journal i digesty se uchovávají 90 dní.

## Důsledky

Denní souhrn lze deterministicky znovu sestavit jen do okamžiku vytvoření
digest snapshotu; archiv pak zobrazuje právě neměnný odesílaný obsah.
Databáze se stává kritickou součástí evidence provozních milníků, zatímco
Slack zůstává best-effort distribuční kanál.

## Přijaté riziko

Při timeoutu Slacku po faktickém přijetí zprávy může retry vytvořit
transportní duplicitu. Aplikace ji nepředchází externím idempotency klíčem,
protože Slack incoming transport takovou garanci neposkytuje.
