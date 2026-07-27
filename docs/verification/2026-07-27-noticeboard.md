# Virtuální nástěnka – ověření

## Verdikt

- Stav: verified, ready for review
- Blockers: none
- Deferred scope: none

## Ověřený rozsah

- store-scoped CRUD pro admina i omezeného uživatele
- sanitizovaný rich text a odmítnutí prázdného nebo nadlimitního obsahu
- optimistický zámek bez přepsání novější změny
- privátní upload, výměna, odebrání a autorizované čtení obrázku
- aktivní, expirované a admin-only košové filtrování, hledání, štítek a stránkování po 24
- soft-delete, obnova, definitivní smazání a denní 30denní úklid
- Tiptap editor, sticky-note mřížka, modaly a zachování původního dashboardu
- společný E2E tok: omezený uživatel vytvoří kartičku a admin stejné prodejny ji upraví
- plochá nástěnka bez vnořeného panelu, systémové zaoblení karet a kompaktní
  dashboard s odkazem na detailní statistiky
- jediný nadpis Nástěnka, první pozice v sekci Prodejna a centrální pill
  aktivní prodejny na store-scoped stránkách

## Čerstvé důkazy

- `make fix`: passed
- `make check`: passed
    - PHPStan level max: passed
    - Prettier a Pint: passed
    - Composer a npm security audit: 0 advisories
    - TypeScript type-check a produkční Vite build: passed
    - frontend unit tests: 19 passed
    - PHP test suite: 574 passed, 11 499 assertions
- cílené noticeboard/dashboard testy po rozšíření hraničního pokrytí: 23 passed, 129 assertions
- Playwright Chromium dashboard E2E: 3 passed (jediný nadpis, desktopová a
  mobilní navigace, store pill na store-scoped stránkách, skrytí ve Správě,
  přepnutí prodejny, sdílená editace a editor/náhled/detail/filtry/validace)

## Známá rizika

- Změny v jiných otevřených prohlížečích se záměrně projeví až po obnovení stránky.
- Produkční nasazení musí spustit novou databázovou migraci a mít funkční privátní disk a scheduler.
