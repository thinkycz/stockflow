# Inertia validační chyby — verification record

## Výsledek

- Stav: verified
- Datum: 2026-08-01
- Rozsah: globální validační handler, bezpečný old input, samostatné router akce, formulářové validace a finanční lifecycle

## Automatizované kontroly

- Regrese předčasného uzavření finančního reportu ověřuje návrat na stejné období, session chybu `report`, zachovaného admina a komponentu `income-expenses/Index`.
- Login formulář ověřuje redirect zpět, zachovaný e-mail a nepřenesené heslo.
- Webové feature testy byly převedeny ze stránkové `422` odpovědi na redirect a session errors; JSON požadavky zůstávají beze změny.
- Unit test helperu ověřuje první zprávu, červený toast a zavolání původního `onError` callbacku.
- Unit test helperu ověřuje také hlavičku samostatné akce; controller test potvrzuje, že stejná zpráva dorazí po redirectu v `props.flash.error` a není závislá jen na načasování klientského callbacku.
- Playwright scénář ověřuje, že předčasné uzavření zůstane na „Income & expenses“, zobrazí chybu a nevykreslí Login.

## Výsledky

- `make fix`: passed
- `make check`: passed
    - PHPStan, Prettier a Pint bez chyb
    - Composer a npm audit bez zranitelností
    - TypeScript a produkční build úspěšné
    - frontend unit testy: 44 passed
    - PHP testy: 617 passed, 14 250 assertions
- `php artisan test tests/Feature/App/Http/Controllers/Api`: 41 passed, 140 assertions
- `npm run e2e -- tests/e2e/income-expenses.spec.ts`: 1 passed

## Závěr

Původní uživatelský scénář je opravený a ověřený na úrovni controlleru i skutečného prohlížeče. Webové Inertia validace se vracejí na původní stránku, autentizace zůstává zachovaná a samostatné akce zobrazují první zprávu v trvalém červeném toastu. JSON/API kontrakt zůstal beze změny.
