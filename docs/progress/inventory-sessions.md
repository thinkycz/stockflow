# Progress: Inventory sessions (Inventury)

> Viz: [Spec](../specs/inventory-sessions.md) · [Plan](../plans/inventory-sessions.md) · [Verification](../verification/inventory-sessions.md)

## Stav fází

| Fáze | Název                      | Stav          | Poznámka                                                                                                                                                                                                                                                                                                 |
| ---- | -------------------------- | ------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0    | Příprava                   | ✅ Hotovo     |                                                                                                                                                                                                                                                                                                          |
| 1    | Datový model (DB + modely) | ✅ Hotovo     | 4 migrace + 2 modely + 2 factory, `InventoryCount` smazán.                                                                                                                                                                                                                                              |
| 2    | Service a business logika  | ✅ Hotovo     | `InventorySessionService` s `createSession`, `previousQuantity`, `buildStoreView` (abecední třídění), `historyForUser`, `buildSessionView`, `consumptionLastDays`, `predictedRunOut`, `sparklineForItem`. Starý `InventoryCountService` smazán. Všechny controllery aktualizovány na nový service.     |
| 3    | Controllers a routes       | ✅ Hotovo     | Nový `InventoryCountShowController`, route `GET /inventory-counts/{session}`. Update controller přesměruje na show page. Store detail rozšířen o `avg_daily_consumption` a `days_until_restock`.                                                                                                       |
| 4    | Frontend (Vue + i18n)      | ✅ Hotovo     | `Index.vue` (nové sloupce: aktuální/poslední/nové množství), `History.vue` (sessions seznam), `Show.vue` (read-only), `stores/Show.vue` (avg/days), i18n cs/en/sk.                                                                                                                                       |
| 5    | Testy                      | ✅ Hotovo     | `InventorySessionServiceTest` (13 testů), `InventorySessionTest` (3 testy), `InventoryCountShowControllerTest` (3 testy), přepsány `InventoryCountUpdateControllerTest` (5) a `InventoryCountHistoryControllerTest` (4). 373 feature testů prochází.                                                    |
| 6    | Dokumentace a release      | ✅ Hotovo     | `CHANGELOG.md`, `docs/architecture.md`, `docs/application_documentation.md` aktualizovány. `make fix` i `make check` (PHPStan, Pint, Prettier) čisté. `npm run build` OK. `audit` hlásí pre-existing Guzzle advisories (mimo scope této změny).                                                          |

## Traceability matice

| Požadavek (spec)                  | Fáze    | Stav |
| --------------------------------- | ------- | ---- |
| `inventory_sessions` tabulka      | 1.1     | ✅   |
| `inventory_session_items`         | 1.2     | ✅   |
| Migrace `inventory_counts`        | 1.3     | ✅   |
| Drop `inventory_counts`           | 1.4     | ✅   |
| Modely `InventorySession`         | 1.5     | ✅   |
| Model `InventorySessionItem`      | 1.6     | ✅   |
| Factory                           | 1.7     | ✅   |
| Smazat `InventoryCount` model     | 1.8     | ✅   |
| Service `InventorySessionService` | 2.1     | ✅   |
| Editor controller                 | 3.1     | ✅   |
| Save controller                   | 3.2     | ✅   |
| History controller                | 3.3     | ✅   |
| Show controller                   | 3.4     | ✅   |
| Route `inventory-counts/{id}`     | 3.5     | ✅   |
| Index.vue (nový layout)           | 4.1     | ✅   |
| History.vue (sessions)            | 4.2     | ✅   |
| Show.vue (read-only)              | 4.3     | ✅   |
| Store Show.vue (avg/days)         | 4.4     | ✅   |
| i18n (cs/en/sk)                   | 4.5     | ✅   |
| Smazat staré testy                | 5.1     | ✅   |
| Nové testy modelu/service         | 5.2     | ✅   |
| Update controller testů           | 5.3-5.5 | ✅   |
| Nový test Show controller         | 5.6     | ✅   |
| Update StoreShow test             | 5.7     | ✅   |
| CHANGELOG                         | 6.1     | ✅   |
| architecture.md                   | 6.2     | ✅   |
| application_documentation.md      | 6.3     | ✅   |
| `make fix`                        | 6.4     | ✅   |
| `make check`                      | 6.5     | ✅   |
| `npm run build`                   | 6.6     | ✅   |
| verification.md                   | 6.7     | ✅   |

## Bloky / rozhodnutí

Žádné aktivní bloky. Všechna klíčová rozhodnutí byla učiněna při
grilování (viz spec.md "Kontext" sekce).

## Rozhodnutí z grilování

1. **Datový model**: `inventory_sessions` + `inventory_session_items`
   (normalizovaný, ne přidání `session_id` do `inventory_counts`).
2. **Migrace**: Přesun dat z `inventory_counts` do nových tabulek + drop.
3. **Editovatelnost**: Read-only historie. Show page je neměnná.
4. **Třídění**: V editoru i show page vždy abecedně podle `items.title`.
5. **Přesměrování po uložení**: Na `/inventory-counts/{newId}`.

## Log

- **2026-06-24 23:30** — Spec, plan a progress vytvořeny po grilování.
  Čeká se na spuštění Fáze 1.
- **2026-06-24** — Fáze 1 dokončena: 4 migrace, 2 modely, 2 factory,
  `InventoryCount` smazán.
- **2026-06-24** — Fáze 2 dokončena: `InventorySessionService` vytvořen,
  `InventoryCountService` smazán, controllery aktualizovány. PHPStan OK.
- **2026-06-24** — Fáze 3 dokončena: `InventoryCountShowController`,
  route `inventory-counts.show`, update controller přesměruje na show.
- **2026-06-24** — Fáze 4 dokončena: `Index.vue` má nové sloupce
  Aktuální/Poslední/Nové množství, `History.vue` zobrazuje sessions,
  `Show.vue` je read-only, `stores/Show.vue` má avg/days sloupce,
  i18n cs/en/sk aktualizováno.
- **2026-06-24** — Fáze 5 dokončena: nové testy, staré smazány,
  373 feature testů prochází.
- **2026-06-24** — Fáze 6 dokončena: dokumentace, `make fix`,
  `make check` (PHPStan/Pint/Prettier OK), `npm run build` OK.
  `audit` hlásí pre-existing Guzzle advisories.
