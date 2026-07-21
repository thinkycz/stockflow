# Docházka brigádníků – implementační plán

## Fáze 1: Doména a persistence

- Migrace, modely, továrny, enum akcí a validity.
- Transakční stavový automat, párování směn, snapshoty, obsazenost a stale stav.
- Modelové a servisní testy vedené TDD.

## Fáze 2: Webové rozhraní a výkazy

- Index a stavové akce pro admina i omezený účet.
- Adminské vytvoření, změna a zneplatnění s auditem.
- Měsíční report a tisková Inertia stránka.
- Controller feature testy pro oprávnění, tenancy a výpočty.

## Fáze 3: Frontend

- Navigace pod Směnami, provozní panel, select, kontextová tlačítka a varování.
- Timeline, stav prodejny, adminský report, korekční formuláře a tisk.
- CS/EN/SK překlady, pravidelný partial reload, type-check a build.

## Fáze 4: Closeout

- Aktualizace architektury a dokumentace.
- Cílené testy, `make fix`, `make check`, kontrola specifikace a release readiness.
