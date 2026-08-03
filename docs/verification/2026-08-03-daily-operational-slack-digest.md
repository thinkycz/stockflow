# Verifikace denního provozního Slack souhrnu

## Verdikt

Implementace je 2026-08-03 připravená k review a nasazení po provedení
migrací. Lokální aplikační, asynchronní a browser vrstvy jsou ověřené.
Reálné doručení do externího Slack workspace nebylo provedeno.

## Ověřený rozsah

- Journal vzniká bez ohledu na Slack konfiguraci, rollbackuje s business
  transakcí a transfer ukládá právě jednou s oběma perspektivami.
- Builder pokrývá všechny enum typy, pražské kalendářní hranice včetně
  letního času, privacy whitelist, heartbeat lokace, transfer směry,
  receptové skóre a voucher batch součty.
- Scheduler vytváří nejstarší chybějící den nejvýše jednou za hodinu,
  respektuje datum aktivace a denně prořezá 90denní historii.
- Queued notifikace je `afterCommit`, má pět pokusů a rostoucí backoff;
  `NotificationSent` označí `sent` a vyčerpaný queue callback `failed`.
- Chybějící token nebo firemní kanál vytváří dohledatelný failed
  digest bez dopadu na již potvrzené business operace.
- Admin archiv, detail, company scope, omezený retry a CS/SK/EN parita.

## Čerstvé důkazy

- Cílená regresní sada: 27 testů, 163 assertions.
- Controller a architecture follow-up: 11 testů, 134 assertions.
- `make fix`: Prettier a Pint dokončeny.
- `make check`: úspěch.
    - PHPStan: 473 souborů, 0 chyb.
    - Prettier a Pint: bez odchylek.
    - Composer/npm audit: 0 známých zranitelností.
    - Frontend unit: 46 testů v 15 souborech.
    - TypeScript type-check a Vite production build: úspěch.
    - Pest: 714 testů, 19 861 assertions.
- `php artisan schedule:list`: creation job je hodinový; prune job je denní
  v 04:15 podle aplikačního schedule timezone.
- Playwright CLI smoke na čerstvé testing databázi:
    - seeded admin se přihlásil,
    - `/settings` zobrazil odkaz na archiv,
    - kliknutí otevřelo `/settings/slack-digests`,
    - archiv zobrazil správný title, empty state a funkční zpětný odkaz.

## Nalezené a opravené při verifikaci

- Browser smoke odhalil neexistující Ziggy route `settings.index`; odkaz nyní
  používá existující `settings.show`.
- První kompletní gate odhalil chybějící per-controller test soubory a
  docblock; po opravě byl celý gate zopakován od začátku.

## Zbývající provozní krok

Po nasazení migrací a konfiguraci bot tokenu a firemního kanálu odeslat
jeden kontrolovaný digest do reálného Slack workspace a potvrdit oprávnění
bota, render Block Kitu a chování produkčního queue workeru.
