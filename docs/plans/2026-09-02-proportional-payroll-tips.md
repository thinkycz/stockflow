# Poměrné rozdělení spropitného

## Status

- Current phase: dokončeno
- Overall status: completed
- Last updated: 2026-09-02
- Pre-existing worktree changes: none

## Požadavky

- [x] Na otevřeném měsíčním přehledu výplat zobrazit tlačítko „Rozdělit spropitné“.
- [x] V modálním okně umožnit zadat jednu celkovou částku v Kč.
- [x] Rozdělit částku mezi pásky s kladným počtem placených hodin poměrem jejich placených hodin.
- [x] Vytvořit pro každého zahrnutého brigádníka s nenulovým haléřovým podílem samostatnou položku spropitného.
- [x] Zaokrouhlit podíly na haléře a deterministicky dorovnat zbytek tak, aby součet přesně odpovídal vstupu.
- [x] Odmítnout uzavřený report, neplatnou částku, cizí prodejnu a měsíc bez kladných placených hodin.
- [x] Zachovat shodné anglické, české a slovenské překlady.
- [x] Pokrýt servis, controller, UI tok a celý projekt čerstvou verifikací.

## Rozhodnutí

- Váhou jsou existující `payable_hours`: plánované hodiny, nebo ruční měsíční override. Skutečná docházka zůstává podle současné mzdové specifikace kontrolním údajem.
- Brigádník s nulovými placenými hodinami nedostane nulovou databázovou položku a do rozdělení se nezapočítá.
- Každé spuštění vytvoří novou dávku běžných úprav typu `tip`; dřívější spropitné se nepřepisuje.
- Položky dostanou lokalizovaný důvod „Poměrně rozdělené spropitné“ a zůstávají spravovatelné na detailu pásky.
- Pro největší zbytky po celočíselném výpočtu haléřů rozhoduje nejprve zbytek a při shodě ID brigádníka. Tím je výsledek stabilní a přesný.
- Nová mutace je dostupná také přes schvalovanou akci distribute_payroll_tips, která používá stejný servis a validační pravidla jako UI.

## Fáze

### 1. Doména a HTTP

- [x] Přidat selhávající servisní test pro poměr a přesný haléřový součet.
- [x] Implementovat transakční rozdělení v `PayrollReportService`.
- [x] Přidat validovaný admin-only controller a route.
- [x] Pokrýt HTTP úspěch, validaci a uzamčený report.

### 2. Přehled výplat

- [x] Přidat tlačítko, modal, částku, kontext pracovníků/hodin a chybové stavy.
- [x] Doplnit frontendové a backendové překlady ve třech jazycích.
- [x] Rozšířit payroll E2E o poměrné rozdělení.

### 3. Verifikace

- [x] Spustit cílené testy, `make fix`, `make check` a payroll Playwright scénář.
- [x] Zapsat release evidence pod `docs/verification/`.

## Blockers

- None.
