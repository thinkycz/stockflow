# Plan: Inventory sessions (Inventury)

> Viz spec: [docs/specs/inventory-sessions.md](../specs/inventory-sessions.md)

## Fáze 0 — Příprava (scaffolding)

- [ ] Vytvořit progress tracker: `docs/progress/inventory-sessions.md`
- [ ] Vytvořit verification doc: `docs/verification/inventory-sessions.md`

## Fáze 1 — Datový model (DB + modely)

- [ ] **1.1** Vytvořit migraci `2026_06_24_000010_create_inventory_sessions_table.php` - Sloupce: `id`, `user_id` FK, `store_id` FK, `counted_at` timestamp, `created_by` FK NULL, `note` NULL, timestamps - Indexy: `(user_id, store_id, counted_at)`
- [ ] **1.2** Vytvořit migraci `2026_06_24_000011_create_inventory_session_items_table.php` - Sloupce: `id`, `session_id` FK cascade, `item_id` FK restrict, `quantity` unsigned int, `note` NULL, timestamps - Index `(session_id, item_id)`
- [ ] **1.3** Vytvořit migraci `2026_06_24_000012_migrate_inventory_counts_to_sessions.php` - `up`: group by `(user_id, store_id, counted_at)` → sessions; copy items - `down`: nelze (informační vyhození, nebo částečný obnovení)
- [ ] **1.4** Vytvořit migraci `2026_06_24_000013_drop_inventory_counts_table.php`
- [ ] **1.5** Vytvořit `app/Models/InventorySession.php` (extends BaseModel, scopes, getters, relace)
- [ ] **1.6** Vytvořit `app/Models/InventorySessionItem.php` (extends BaseModel, scopes, getters, relace)
- [ ] **1.7** Vytvořit `database/factories/InventorySessionFactory.php` + `InventorySessionItemFactory.php`
- [ ] **1.8** Smazat `app/Models/InventoryCount.php` + `database/factories/InventoryCountFactory.php`
- [ ] **1.9** Spustit `php artisan migrate:fresh --seed` a ověřit že DB je funkční
- [ ] **1.10** Spustit `php artisan test` — musí selhat (testy ještě odkazují na `InventoryCount`), ale DB migrace musí projít.

## Fáze 2 — Service a business logika

- [ ] **2.1** Vytvořit `app/Services/InventorySessionService.php` (přesun logiky z `InventoryCountService`)
    - `createSession(User, Store, array<row>, ?note): InventorySession` — transakce
    - `buildStoreView(User, Store): array` — pro editor (řadí abecedně)
    - `previousQuantity(Store, Item, ?Carbon before): ?int`
    - `historyForUser(...)` — seznam sessions
    - `buildSessionView(User, InventorySession): array` — read-only data
- [ ] **2.2** Smazat `app/Services/InventoryCountService.php`
- [ ] **2.3** Spustit `php artisan test` — service testy musí projít, controller testy mohou selhat

## Fáze 3 — Controllers a routes

- [ ] **3.1** Přejmenovat / upravit `InventoryCountIndexController`
    - Vrací: store, stores, items, current_quantities map, last_quantities map, default values
- [ ] **3.2** Přepsat `InventoryCountUpdateController` — `createSession` flow
    - Po uložení redirect na `inventory-counts.show`
- [ ] **3.3** Přepsat `InventoryCountHistoryController` — seznam sessions
- [ ] **3.4** Vytvořit `InventoryCountShowController` — read-only detail
- [ ] **3.5** Přidat route `GET /inventory-counts/{session}` (`whereNumber`) → Show
- [ ] **3.6** Spustit `php artisan test` — controller testy musí projít

## Fáze 4 — Frontend (Vue + i18n)

- [ ] **4.1** `resources/js/pages/inventory-counts/Index.vue` — nový layout editoru
    - Sloupce: Položka, SKU, Jednotka, Aktuální množství, Poslední množství, Nové množství, Poznámka
    - Třídění abecedně (server-side)
    - Odebrat avg_consumption, days_until_restock, sparkline, status, history tab
- [ ] **4.2** `resources/js/pages/inventory-counts/History.vue` — seznam sessions
- [ ] **4.3** `resources/js/pages/inventory-counts/Show.vue` (nový) — read-only detail
- [ ] **4.4** `resources/js/pages/stores/Show.vue` — přidat sloupce Průměrná spotřeba, Dní do vyprodání
- [ ] **4.5** i18n: aktualizovat `cs.json`, `en.json`, `sk.json`
    - Přidat: `inventory_counts.sessions.*`, `inventory_counts.sessions.show.*`
    - Přidat: `stores.columns.avg_daily_consumption`, `stores.columns.days_until_restock`
    - Upravit: `inventory_counts.columns.current` → `new_quantity`
    - Přidat: `inventory_counts.columns.current_readonly`, `last_quantity`

## Fáze 5 — Testy

- [ ] **5.1** Smazat `InventoryCountTest.php`, `InventoryCountServiceTest.php`
- [ ] **5.2** Vytvořit `InventorySessionTest.php`, `InventorySessionServiceTest.php`
- [ ] **5.3** Aktualizovat `InventoryCountIndexControllerTest.php` — asserce nových sloupců
- [ ] **5.4** Aktualizovat `InventoryCountUpdateControllerTest.php` — asserce vytvoření session
- [ ] **5.5** Aktualizovat `InventoryCountHistoryControllerTest.php` — asserce seznamu sessions
- [ ] **5.6** Vytvořit `InventoryCountShowControllerTest.php`
- [ ] **5.7** Aktualizovat `StoreShowControllerTest.php` — asserce nových sloupců
- [ ] **5.8** `php artisan test` — všechny musí projít

## Fáze 6 — Dokumentace a release

- [ ] **6.1** Aktualizovat `CHANGELOG.md` — `### Added (inventory sessions)`, `### Removed (inventory counts)`
- [ ] **6.2** Aktualizovat `docs/architecture.md` — sekce Inventory sessions
- [ ] **6.3** Aktualizovat `docs/application_documentation.md` — sekce Inventury
- [ ] **6.4** Spustit `make fix` — všechny formatters projdou
- [ ] **6.5** Spustit `make check` — PHPStan + Pint + Prettier + type-check + testy čisté
- [ ] **6.6** Spustit `npm run build` — vyprodukovat production assety
- [ ] **6.7** Vyplnit `docs/verification/inventory-sessions.md` — důkazy

## Paralelní / nezávislé práce

- Fáze 1 (DB + modely) může běžet paralelně s Fáze 4 (UI) — obě se sbíhají v Fázi 3.
- Fáze 5 (testy) závisí na Fázi 3 a Fázi 4.

## Odhad rozsahu

| Fáze | Soubory (přibližně)           | Náročnost |
| ---- | ----------------------------- | --------- |
| 0    | 2 nové .md                    | nízká     |
| 1    | 5 nových, 2 smazané           | střední   |
| 2    | 1 nový, 1 smazaný             | střední   |
| 3    | 3 upravené, 1 nový            | střední   |
| 4    | 4 upravené, 1 nový            | vysoká    |
| 5    | 4 upravené, 3 nové, 2 smazané | vysoká    |
| 6    | 3 upravené, 1 nový            | nízká     |
