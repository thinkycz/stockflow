# Slack odkaz vrací hostovi 401

## Symptom

Nepřihlášený uživatel po otevření odkazu `shifts.index` ze Slack notifikace
dostane odpověď 401 místo přesměrování na přihlášení.

## Evidence

- Slack notifikace odkazuje na chráněnou webovou `GET /shifts` routu.
- Routa používá sdílený middleware `EnsureInertiaUserIsAuthenticated`.
- Middleware vracel 401 pro každý požadavek preferující JSON bez ohledu na
  bezpečnost HTTP metody.
- Regresní test s `GET` a `Accept: application/json` reprodukoval
  `AuthenticationException` přímo v tomto middleware.
- Běžné browser testy problém nezachytily, protože navigace bez JSON preference
  už před opravou dostávala redirect.

## Root Cause

Webový auth middleware nerozlišoval bezpečnou navigaci `GET/HEAD` od JSON
mutací. Externí prohlížeč s JSON-preferring hlavičkou proto dostal API chování,
přestože otevíral webovou stránku.

## Fix

Bezpečné webové metody jsou při chybějícím přihlášení vždy přesměrovány na
login. Odpověď 401 zůstává zachovaná pro nepřihlášené JSON mutace.

## Prevence

Testovací kontrakt auth middleware nyní pokrývá oba případy odděleně podle
bezpečnosti HTTP metody, ne pouze podle hlavičky `Accept`.
