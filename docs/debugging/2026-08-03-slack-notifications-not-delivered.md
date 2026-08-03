# Slack notifikace se nedoručují – diagnostika

## Symptom

Po nasazení denního provozního souhrnu uživatel hlásí, že Slack notifikace
přestaly chodit.

Uživatel následně upřesnil, že výpadek nastal na stagingu nebo v produkci.
Lokální runtime zjištění jsou proto pouze kontrolní větev a neurčují příčinu
živého výpadku.

## Přístup k živému prostředí

- Produkční deployment je `sklad.teacha.cz` na Forge serveru
  `oracle-ampere`; žádný samostatný StockFlow staging web není v organizaci
  evidovaný.
- Veřejný runtime obsahuje nové Slack digest routes a aktuální aplikaci, takže
  starý poslední deployment log z 11. června není fingerprint běžícího kódu.
- Admin UI potvrzuje uložený firemní Slack kanál a kanál u všech čtyř aktivních
  lokací.
- Archiv digestů je zatím prázdný očekávaně: aktivace proběhla 3. srpna a první
  úplný den ještě neskončil.
- Forge eviduje Redis queue worker jako nainstalovaný, ale jeho log je prázdný.
- Starší Forge CLI vrací u vzdáleného `command` nesprávný dřívější výsledek a
  přímý SSH klíč není na serveru autorizovaný. Stav tokenu, `failed_jobs`, Redis
  fronty, journalu, scheduler cron entry a odpovědi Slack API proto zatím není
  ověřený.

## Ověřená příčina živého výpadku

Produkční Redis worker byl procesně `RUNNING`, ale frontu neodebíral:

- před restartem měl worker PID `1914344` a běžel od 09:36 UTC;
- Redis obsahoval 21 ready jobů, 0 delayed a 0 reserved;
- 16 položek byly `OperationalActivitySlackNotification` a 5 položek
  `CreateDailyOperationalDigestJob`; všechny měly `attempts=0`;
- worker nebyl pozastavený a aplikace nebyla v maintenance režimu;
- journal mezitím správně uložil 19 provozních událostí;
- token byl platný a aktuálně měl `chat:write` scopes.

Po ručním restartu vznikl nový worker PID `1923404`, fronta klesla na 0 bez
nových failed jobů a uživatel potvrdil doručení Slack zpráv. A/B kontrola tedy
prokazuje provozní příčinu: předchozí worker byl živý podle supervisoru, ale
nepolloval aktuální ready frontu. Přesný interní důvod tohoto stavu nelze z
prázdného quiet worker logu zpětně určit.

V `failed_jobs` zůstává jeden historický záznam z 22. července s chybou
`missing_scope`. Aktuální `auth.test` potvrdil `chat:write`, takže nejde o
příčinu srpnového výpadku ani o novou chybu po restartu.

## Prevence opakování

- Restartovat queue workery při každém release a po změně runtime konfigurace.
- Monitorovat stáří nejstaršího ready jobu, ne pouze supervisor stav `RUNNING`.
- Alertovat na nenulový `failed_jobs` a rostoucí ready backlog.
- Zachovat provozní journal jako auditní kontrolu, že business událost vznikla,
  i když okamžitý Slack transport čeká ve frontě.

## Bezpečnostní poznámka

Forge CLI při identifikaci repozitáře neočekávaně vypsalo credential vložený v
Git remote URL. Hodnota není v tomto dokumentu ani dalších výstupech opakována;
credential je nutné rotovat a remote změnit na SSH nebo URL bez embedded tokenu.

## Ověřený runtime stav

Read-only kontrola lokálního prostředí 2026-08-03:

- všechny databázové migrace jsou aplikované;
- queue connection je `sync`;
- `SLACK_BOT_USER_OAUTH_TOKEN` není v načtené konfiguraci nastavený;
- hlavní administrátor nemá nastavený firemní Slack kanál;
- žádná prodejna nemá nastavený store Slack kanál;
- queue nemá pending ani failed joby;
- journal ani archiv digestů zatím nemají záznamy;
- lokálně neběží samostatný `queue:work`, `queue:listen` ani
  `schedule:work` proces. Pro `sync` queue worker není potřeba, scheduler ale
  musí spouštět cron nebo `schedule:work`.

Aktuální root `User` načítá `company_slack_channel` i
`operational_digest_started_on`. Starší lokální log obsahuje tři chyby
nenačteného `company_slack_channel`, ale tento stav po současných migracích
není reprodukovatelný.

## Kontrolní test

Cílená sada všech producentů, listenerů a obou Slack rendererů prošla:

- 112 testů;
- 444 assertions;
- ověřen store i company routing, post-commit dispatch, rollback, selhání
  enqueue, denní digest a jeho `sent`/`failed` lifecycle.

## Root cause pro kontrolované lokální prostředí

Slack routing je zablokovaný chybějící konfigurací. Bez bot tokenu listener
okamžitých notifikací záměrně nic neodešle. Bez store nebo firemního kanálu
navíc neexistuje cílová destination.

## Důležitá garance

Okamžité Slack zprávy jsou best-effort a nikdy nesmějí zneplatnit business
operaci. Nejsou tedy garantovaně odeslány „vždy“. Denní digest na rozdíl od
nich ukládá při chybějícím tokenu nebo kanálu stav `failed`, který lze po
opravě konfigurace ručně retryovat v archivu.

## Provozní náprava

1. Nastavit `SLACK_BOT_USER_OAUTH_TOKEN` v cílovém deploymentu.
2. V administraci nastavit firemní kanál a na jednotlivých prodejnách jejich
   store kanály.
3. Vyčistit config cache a restartovat dlouho běžící queue workery, aby novou
   konfiguraci načetly.
4. V produkci ověřit Redis queue worker a minutely Laravel scheduler.
5. Odeslat kontrolovanou provozní událost a potvrdit zprávu přímo ve Slacku;
   samotný stav `queued` není důkaz doručení.

## Neověřená hranice

Tato diagnostika nevidí konfiguraci ani procesy jiného produkčního serveru.
Pokud se hlášení týká jiného deploymentu, je nutné zopakovat stejné
read-only kontroly přímo tam a zkontrolovat Slack API odpověď nebo worker log.
