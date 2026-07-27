# Docházka brigádníků – ověření

## Výsledek

Funkcionalita je implementovaná a její aplikační, doménové i prezentační kontroly procházejí. Release verdict je dočasně **not ready** výhradně kvůli bezpečnostnímu auditu existující Composer závislosti.

## Ověřené oblasti

- Transakční příchod, zahájení a ukončení pauzy, přímý odchod z pauzy a odchod.
- Neplatné přechody, opakovaný příchod, stale blok a databázové invarianty otevřeného bloku a pauzy.
- Párovací okno směny včetně hranic −60/+60 minut, potvrzení mimo směnu a snapshot plánovaných údajů a sazby.
- Izolace omezeného účtu na přiřazenou prodejnu a admin-only opravy, výkazy a tisk.
- Agregovaná obsazenost, měsíční ořez přes půlnoc, pauzy, přesné sekundy, odchylka a odměna.
- Auditované create/update/void opravy s důvodem a neměnnými auditními záznamy.
- CS/EN/SK parita, TypeScript type-check a produkční frontend build.

## Evidence

- `make fix`: passed.
- `make check`: PHPStan (303 souborů), Prettier a Pint prošly; příkaz se zastavil až na `composer audit`.
- `npm run type-check`: passed.
- `npm run build`: passed.
- `npm run test:unit`: 6 files, 14 tests passed.
- `make test`: 510 tests passed po doplnění finálních hraničních testů docházky.
- Cílená attendance sada: service, report, correction, controller a architecture testy prošly.

## Release blocker

`composer audit` hlásí čtyři medium advisory pro zamčený `guzzlehttp/guzzle` 7.13.2. Opravená řada začíná podle auditu verzí 7.15.1. Závislost nebyla v rámci této funkce měněna, aby se bezpečnostní upgrade a jeho regresní ověření nemíchaly s implementací docházky.

## Doporučený další krok

Samostatně aktualizovat Guzzle na kompatibilní opravenou verzi a znovu spustit `make check`.
