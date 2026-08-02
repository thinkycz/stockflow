# Ověření posouzení odchylek docházky

## Výsledek

Implementace odpovídá specifikaci: odchylka nad 15 minut je auditovaně
schválitelná nebo zamítnutelná, schválení synchronizuje směnu a snapshoty a
otevřená výplata se přepočítá. Uzavřená výplata schválení blokuje.

## Čerstvé důkazy

- `make check` — PHPStan bez chyb, Prettier a Pint prošly, Composer/NPM audity
  bez nálezů, type-check a produkční build prošly, 14 Vitest souborů / 44 testů
  a 641 Pest testů / 15 797 assertions prošlo.
- `./node_modules/.bin/playwright test tests/e2e/attendance.spec.ts --grep
'rejects and then approves'` — 1 Chromium test prošel nad čerstvou migrací a
  E2E seedem; ověřil zamítnutí, revizi, schválení a výplatní základ 8,25 hodiny.
- Cílené servisní a controller testy ověřily hranici 900/901 sekund, více
  pracovních bloků, neměnný audit, zastarání rozhodnutí, překryv, tenantové
  oprávnění a uzavřený výplatní report.

## Poznámka

První paralelní běh jednoho Inertia controller testu se střetl se souběžnou
změnou Vite manifestu a vrátil 409. Samostatné opakování po dokončení buildu
prošlo; následný sériový `make check` prošel celý.
