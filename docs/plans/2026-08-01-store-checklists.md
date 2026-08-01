# Checklisty provozovny – implementační plán

## Fáze 1: Doména a snapshoty

- Přidat tabulky, enumy, modely, factory a validity.
- Přidat přesný výchozí katalog z obou PDF.
- Implementovat idempotentní inicializaci šablon, denní snapshoty, stavy a auditní události.

## Fáze 2: HTTP a oprávnění

- Přidat admin stránku šablon/historie a transakční uložení skupiny.
- Přidat dnešní dashboard payload, verzovaný toggle a omluvení dne.
- Zapojit inicializaci nové retail provozovny a scheduler.

## Fáze 3: UI

- Přidat sidebar položku pod Docházku pouze adminovi.
- Přidat editor šablon, historii/detail a dvě dashboardové kartičky.
- Doplnit synchronní CS/SK/EN překlady a store-scoped klasifikaci.

## Fáze 4: Ověření

- Pokrýt katalog, snapshoty, autorizaci, časové hranice, audit a souběh.
- Pokrýt hlavní UI cestu E2E.
- Spustit cílené testy, type-check/build, `make fix` a `make check`.
