# Ověření přesměrování Slack odkazu na login

## Claim

Nepřihlášený uživatel, který otevře odkaz na směny ze Slack notifikace, je
přesměrován na `/login`, i když klient preferuje JSON. Nepřihlášené JSON
mutace nadále vracejí 401.

## Evidence

- Regresní middleware test reprodukoval původní `AuthenticationException` pro
  bezpečný `GET` s `Accept: application/json`.
- Po opravě prošly všechny čtyři testy
  `EnsureInertiaUserIsAuthenticatedTest`, včetně redirectu bezpečného GET a
  zachované 401 pro JSON POST.
- Integrační test skutečné routy
  `/shifts?year=2026&month=9` s JSON preference prošel a ověřil redirect na
  `/login`.
- Nezávislé API testy nepřihlášených požadavků na profil a změnu hesla nadále
  vracejí 401.
- `make fix && make check` prošlo: PHPStan, formátování, audity, frontend
  type-check/build, unit/feature testy a 85 E2E testů.
- Jeden nesouvisející receptový E2E test uspěl na retry a následně samostatně
  prošel napoprvé.

## Verdikt

Požadované přesměrování je ověřené na sdíleném middleware i na konkrétní
směnové routě. API autentizační kontrakt zůstal zachovaný.
