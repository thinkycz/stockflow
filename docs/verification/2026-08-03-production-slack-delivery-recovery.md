# Ověření obnovení produkčních Slack notifikací

## Claim

Po restartu produkčního Redis queue workeru se backlog zpracoval a Slack
notifikace se znovu doručují.

## Runtime evidence

- Deployment: `sklad.teacha.cz`, commit `264db63`.
- Migrace pro firemní Slack, provozní journal a denní digest jsou aplikované.
- Bot token je načtený; Slack `auth.test` uspěl a hlásí `chat:write` scopes.
- Firemní kanál i všechny čtyři lokační kanály jsou nakonfigurované.
- Před restartem: 21 ready, 0 delayed, 0 reserved, všechny ready joby měly
  `attempts=0` a starý worker pouze spal.
- Po restartu: nový PID `1923404`, queue size 0, žádný nový failed job.
- Jediný failed job je historický z 22. července; po restartu nepřibyl další.
- Systémový cron spouští `schedule:run` každou minutu a digest/prune joby jsou
  v Laravel scheduleru registrované.
- Uživatel potvrdil, že zprávy po restartu dorazily do Slacku.

## Verdikt

Obnovení produkčního doručování je ověřené přes queue handoff i výsledný
uživatelský Slack kanál. Denní digest zatím nelze end-to-end ověřit, protože
aktivace proběhla 3. srpna a první celý digestovaný den ještě neskončil.

## Zbývající provozní riziko

Supervisor stav `RUNNING` sám nezaručuje, že worker frontu polluje. Bez alertu
na stáří ready backlogu se stejný typ výpadku může opakovat bez okamžitého
upozornění.
