# Virtuální nástěnka – implementační plán

## Fáze 1: Doména a bezpečný zápis

- Přidat migraci, enumy, model, factory a validity.
- Přidat HTML sanitizer a službu pro CRUD, optimistický zámek a soubory.
- Pokrýt doménu a zápis feature/unit testy.

## Fáze 2: Webový kontrakt

- Rozšířit dashboard o store-scoped seznam, filtry a stránkování.
- Přidat create/update/delete/image endpointy pro oba typy uživatelů.
- Přidat admin-only koš, obnovu a definitivní smazání.
- Pokrýt autorizaci, filtrování, soubory a konflikty feature testy.

## Fáze 3: Uživatelské rozhraní

- Přidat omezený Tiptap editor a formulářový modal.
- Přidat sticky-note mřížku, detail, filtrování, hledání a stránkování.
- Zachovat současný obsah dashboardu pod nástěnkou.
- Doplnit frontendové a E2E scénáře.

## Fáze 4: Životní cyklus a uzavření

- Přidat denní 30denní úklid koše.
- Aktualizovat architekturu, aplikační dokumentaci a překlady.
- Spustit cílené testy, frontend type-check/build, `make fix` a `make check`.
- Zapsat ověřovací důkazy a rozhodnout release readiness.
