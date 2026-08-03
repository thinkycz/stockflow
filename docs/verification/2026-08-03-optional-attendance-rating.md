# Volitelné hodnocení docházky – ověření

## Verdikt

- Stav: ready
- Implementace odpovídá schválenému plánu a nemá známé blokátory.
- Před nasazením je nutné aplikovat databázovou migraci.

## Ověřené chování

- Databázový i aplikační výchozí stav je zapnutý pro nové a existující brigádníky.
- Vypnutí skryje a přestane počítat celý rating v interních i veřejných kontraktech.
- Evidence docházky, korekce, plánované hodiny a adminská mzda zůstávají dostupné.
- Souhrnné ratingové počty zahrnují pouze brigádníky se zapnutým hodnocením.
- Opětovné zapnutí obnoví rating z nezměněné historické docházky.
- UI používá stav `disabled`, ikonu `CircleOff`, tooltip, text pro čtečky a pomlčky pro dílčí metriky.
- Formuláře, seznam brigádníků a CS/EN/SK překlady jsou konzistentní.

## Čerstvé důkazy

- `make fix`: prošlo bez dodatečných změn.
- `make check`: prošlo.
    - PHPStan na maximální úrovni bez chyb.
    - Prettier a Pint bez odchylek.
    - Composer a npm audit bez známých zranitelností.
    - TypeScript kontrola a produkční build prošly.
    - Vitest: 46 testů prošlo.
    - Pest: 719 testů, 19 939 assertions prošlo.
- Cílený Playwright scénář `admin disables attendance rating`: 1 test prošel v Chromiu.
- `git diff --check`: prošlo.

## Poznámky k prostředí

- První pokusy o plnou kontrolu a browser test narazily na sandboxové omezení socketu a lokálního portu; stejné příkazy následně proběhly úspěšně s povoleným spuštěním.
