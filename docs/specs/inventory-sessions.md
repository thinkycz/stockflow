# Spec: Inventory sessions (Inventury)

## Účel

Nahradit stávající "flat" model inventur (`inventory_counts` = jeden řádek na
položku) entitou **Inventura** (session), která sdružuje všechny položky
z jednoho fyzického počítání. Uživatel tak bude moci:

- Vytvořit novou inventuru jedním kliknutím (vznikne session + N řádků).
- Otevřít konkrétní inventuru z historie a vidět přesně co a kolik napočítal.
- Při zadávání vidět vedle sebe **Aktuální množství** (realita),
  **Poslední množství** (předchozí inventura) a zapsat **Nové množství**.

## Kontext

Původní `inventory_counts` je append-only log jednotlivých snapshotů.
Při ukládání se vytvořilo N řádků se stejným `counted_at`. Neexistoval
způsob, jak se vrátit k inventuře konkrétního dne bez filtrování.
Editor na `/inventory-counts` míchal analytické sloupce (průměrná
spotřeba, dní do vyprodání, sparkline, stav) se vstupním formulářem.

## Datový model

### Nové tabulky

`inventory_sessions`

- `id` — PK
- `user_id` — FK users (vlastník dat; pro limitované uživatele = admin/parent)
- `store_id` — FK stores
- `counted_at` — timestamp (používá se pro řazení historie)
- `created_by` — FK users (NULL on delete) — kdo fyzicky zadal
- `note` — string NULL
- `created_at`, `updated_at`

`inventory_session_items`

- `id` — PK
- `session_id` — FK inventory_sessions (cascade on delete)
- `item_id` — FK items (restrict)
- `quantity` — unsigned integer
- `note` — string NULL
- `created_at`, `updated_at`

Indexy: `(user_id, store_id, counted_at)` na sessions,
`(session_id, item_id)` na items.

### Migrace ze staré tabulky

- Vytvořit `inventory_sessions` a `inventory_session_items`.
- Pro každou skupinu `(user_id, store_id, counted_at)` v `inventory_counts`
  vytvořit jeden session řádek.
- Pro každý řádek `inventory_counts` vytvořit odpovídající
  `inventory_session_items` řádek.
- Dropnout `inventory_counts`.

## URL / Routes

| Metoda | URL                           | Controller                            | Účel                              |
| ------ | ----------------------------- | ------------------------------------- | --------------------------------- |
| GET    | `/inventory-counts`           | `InventoryCountIndexController`       | Formulář pro novou inventuru      |
| POST   | `/inventory-counts`           | `InventoryCountUpdateController`      | Vytvoří novou session             |
| GET    | `/inventory-counts/history`   | `InventoryCountHistoryController`     | Seznam inventur (místo snapshotů) |
| GET    | `/inventory-counts/{session}` | `InventoryCountShowController` (nový) | Detail konkrétní inventury        |

`{session}` je `whereNumber`, takže nekoliduje s `/history`.

## Frontend

### `/inventory-counts` (editor — nová inventura)

Sloupce (vše v abecedním pořadí podle `items.title`):

| Sloupec               | Zdroj                                                                                             | Typ       |
| --------------------- | ------------------------------------------------------------------------------------------------- | --------- |
| Položka               | `items.title`                                                                                     | text      |
| SKU                   | `items.sku`                                                                                       | text      |
| Jednotka              | `items.unit`                                                                                      | text      |
| **Aktuální množství** | `store_items.quantity` pro danou `(store, item)`                                                  | read-only |
| **Poslední množství** | nejnovější `inventory_session_items.quantity` pro `(store, item)` před dneškem (NULL pokud žádná) | read-only |
| **Nové množství**     | input, předvyplněno `store_items.quantity`                                                        | input     |
| Poznámka              | input                                                                                             | input     |

Akce: tlačítko **Uložit inventuru** — vytvoří novou session, přesměruje
na `/inventory-counts/{newId}`.

### `/inventory-counts/history` (seznam)

Sloupce:

- Datum (cs: `dd.MM.yyyy HH:mm`)
- Obchod
- Počet položek
- Poznámka
- Akce: **Otevřít** → `/inventory-counts/{id}`

### `/inventory-counts/{id}` (detail — read-only)

Hlavička: datum, obchod, autor (created_by email).

Sloupce (stejné jako editor, ale bez inputů):

- Položka, SKU, Jednotka, **Aktuální množství** (= uložená hodnota),
  **Poslední množství**, Poznámka

Tlačítko **Zpět na seznam** (na `/history`).

## Store detail dopad

Na `/stores/{id}` v inventory tabulce se nově zobrazí:

- `Průměrná spotřeba` (avg_daily_consumption, denní spotřeba za 30 dní)
- `Dní do vyprodání` (days_until_restock)

Vedle již existujících: status, sparkline, naposledy napočítáno,
množství, hodnota.

## Přesměrování

- Po uložení (POST /inventory-counts) → `GET /inventory-counts/{newId}`
  (show page nově vytvořené session).

## Terminologie (závazná)

| Uživ. název       | DB / kód                           | Význam                                           |
| ----------------- | ---------------------------------- | ------------------------------------------------ |
| Inventura         | `InventorySession`                 | Jedno fyzické počítání (session + řádky)         |
| Řádek inventury   | `InventorySessionItem`             | `(item_id, quantity, note)` v rámci session      |
| Aktuální množství | `store_items.quantity`             | Skutečné aktuální množství v obchodě (read-only) |
| Nové množství     | `inventory_session_items.quantity` | Hodnota zadaná při inventuře (input)             |
| Poslední množství | předchozí session                  | `quantity` z nejnovější předchozí inventury      |

## Nefunkční požadavky

- 1:1 parita i18n klíčů v `resources/js/i18n/{cs,en,sk}.json`.
- PHPStan `level: max` zůstává — žádné baseline, žádné broad ignores.
- Všechny testy projdou (370+).
- `make fix` + `make check` čisté (kromě pre-existing Guzzle advisories).
- Read-only historie: show page **nemá** input pro množství, pouze zobrazuje.

## Výstupní kritéria (Definition of Done)

- [ ] Existují tabulky `inventory_sessions` a `inventory_session_items`.
- [ ] `inventory_counts` tabulka je dropnutá.
- [ ] `InventorySession` a `InventorySessionItem` modely existují.
- [ ] `InventoryCount` model je odstraněn.
- [ ] `InventorySessionService` nahradil `InventoryCountService` v logice
      budování view + záznamu nové session.
- [ ] `/inventory-counts` editor má sloupce: Položka, SKU, Jednotka,
      Aktuální množství, Poslední množství, Nové množství, Poznámka.
- [ ] `/inventory-counts/history` zobrazuje seznam inventur (ne snapshotů).
- [ ] `/inventory-counts/{id}` zobrazuje read-only detail inventury.
- [ ] `/stores/{id}` zobrazuje nové sloupce Průměrná spotřeba a Dní do vyprodání.
- [ ] 1:1 parita i18n v cs/en/sk.
- [ ] Všechny testy projdou (>= 370).
- [ ] `make fix` a `make check` čisté.
- [ ] CHANGELOG, `docs/architecture.md`, `docs/application_documentation.md`
      aktualizovány.
