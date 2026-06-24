# Inventura + Statistika naskladnění

## 1. Shrnutí

Přidáme dvě nové oblasti do aplikace:

1. **Inventura** — stránka `/inventory-counts?store_id=…` (v hlavním navigačním menu těsně pod „Výkazy"), která pro vybranou pobočku zobrazí tabulku všech položek katalogu s aktuálním počtem. Zaměstnanec může u každé položky přepsat číslo, uložit, a stránka se přepočítá.
2. **Statistika naskladnění** — stránka `/reports/statistics` (pod stávajícím `reports`), která kombinuje data z Výkazů, Inventury a skladových pohybů a zobrazuje grafy (příjem, výdej, spotřeba, odhad data naskladnění).

Datově přidáme tabulku `inventory_counts`, která uchovává historii fyzických počtů. Při uložení inventury se _současně_ přepíše aktuální stav v `store_items`, aby zůstal jediný zdroj pravdy pro „co je teď na skladě“.

### Klíčová rozhodnutí (potvrzeno s uživatelem)

- Datový model: **snapshot inventury s historií** + zápis do `store_items`.

- Období pro průměrnou spotřebu: **posledních 30 dní**, s konfigurovatelnou konstantou (default 30).

- Statistiky a grafy: **nová stránka** **`/reports/statistics`** (zachová stávající `/reports` strukturu).

## 2. Analýza současného stavu

Prostudované soubory (klíčové reference):

- `app/Models/Statement.php`, `app/Models/StatementDay.php` — vzor pro snapshot hlavička + dny. Inventura bude jednodušší: jeden řádek = jeden počet konkrétní položky, žádné „dny“.

- `app/Http/Controllers/Web/Statement/*` — vzor pro index/update/clear kontrolery, `StatementService::findOrCreateForMonth` + `updateDays`.

- `app/Http/Validation/StatementValidity.php` — vzor pro `*Validity` třídu s `BaseValidity` wrappery.

- `app/Models/Store.php` + `app/Models/StoreItem.php` — `store_items` drží aktuální množství; vztah `Store::storeItems()` a `Store::items()`.

- `app/Models/StockMovement.php` + `app/Services/StockMovementService.php` — již existuje logika `OUTGOING`/`INCOMING`/`ADJUSTMENT`. **Pro výpočet spotřeby** budeme agregovat `OUTGOING` pohyby ze `source_store_id = $store->id` za posledních 30 dní.

- `app/Services/StatementService.php::buildReport()` + `ReportController` — vzor pro stavbu reportu (totals, channels, daily řada).

- `resources/js/pages/reports/Index.vue` — vzor pro stránku s `Card` + `Chart` + `DataTable`.

- `resources/js/pages/statements/Index.vue` — vzor pro `Select` výběru pobočky + `useForm` PUT/POST.

- `routes/web.php` — `Resolver::resolveRouteRegistrar()` směrování; kontrolery pro Inventuru i Statistiku se přidají do middleware groupy `EnsureInertiaUserIsAuthenticated`.

- `resources/js/layouts/AppLayout.vue` — pole `navItems` (každá položka má `key`, `href`, `label`, `icon`).

- `lang/{cs,en,sk}.json` a `resources/js/i18n/{cs,en,sk}.json` — klíče se přidávají do všech tří locale najednou (test `tests/Unit/I18nParityTest.php`).

Konvence, které musíme dodržet:

- `use` importy, žádné inline `Statement::` v tělech metod; validace přes `*Validity::inject()`; všechny query skrze `scopeForUser()`; `DB::transaction()` pro zápisy nad 1 řádek; PHPStan `level: max` bez baseline; žádné `env()`/`config()` v `app/`; `getKey()`/`getUserId()`/`getRouteKey()` pro ID; názvy `*Controller`, `*Validity`, `*Service`; web index controller deklaruje `public const int TAKE`.

## 3. Navržené změny

### 3.1 Backend — migrace

**Nový soubor** `database/migrations/2026_06_24_000001_create_inventory_counts_table.php`:

```php
return new class extends Migration {
    public function up(): void {
        Resolver::resolveSchemaBuilder()->create('inventory_counts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('counted_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'store_id', 'counted_at']);
            $table->index(['store_id', 'item_id', 'counted_at']);
        });
    }
};
```

- `counted_at` defaultuje na `now()`; primární klíč jen `id` (neukládáme unique, chceme historii více počtů za den).

- `created_by` = kdo uložil řadu (typicky aktuální uživatel).

### 3.2 Backend — model

**Nový soubor** `app/Models/InventoryCount.php` (mirroruje styl `app/Models/StatementDay.php`):

- `protected $table = 'inventory_counts'`

- Použít trait `BelongsToUser` a `HasFactory<InventoryCountFactory>`.

- Statické scope:

    - `scopeSearch(Builder $query, string $search)` — `noop` (text nevyhledáváme).

    - `scopeForStore(Builder $query, int $storeId)`.

    - `scopeForItem(Builder $query, int $itemId)`.

    - `scopeSince(Builder $query, Carbon $since)` — `where('counted_at', '>=', $since->toDateTimeString())`.

- `querySelect(Builder $query)` vrací `id, user_id, store_id, item_id, quantity, counted_at, created_by, note, created_at, updated_at`.

- Vztahy: `store(): BelongsTo<Store>`, `item(): BelongsTo<Item>`, `creator(): BelongsTo<User>`.

- Gettery: `getStoreId()`, `getItemId()`, `getUserId()`, `getQuantity()`, `getCountedAt(): Carbon`, `getNote(): string|null`, `getCreatedBy(): int|null`.

- `casts(): ['quantity' => 'integer', 'counted_at' => 'datetime']`.

### 3.3 Backend — factory

**Nový soubor** `database/factories/InventoryCountFactory.php`:

- `definition()` nastaví `user_id`, `store_id`, `item_id`, `quantity` (random 0..100), `counted_at`.

- Stavy: `byUser(User)`, `forStore(Store)`, `forItem(Item)`, `recent(int $days)`.

- `note` defaultně `null`.

### 3.4 Backend — validace

**Nový soubor** `app/Http/Validation/InventoryCountValidity.php`:

- `inject(int|null $userId = null)`, `BaseValidity` v konstruktoru.

- `storeId()` — `exists('stores', 'id', ['user_id', $userId])`.

- `itemId()` — `exists('items', 'id', ['user_id', $userId])`.

- `id()` — `exists('inventory_counts', 'id', ['user_id', $userId])`.

- `rows()` — `array(null)->min(1)`.

- `rowItemId()` — viz výše.

- `rowQuantity()` — `integer(999999, 0)` (nula je povolená, ale zaměstnanec může zapsat `0` = vyprodáno).

- `rowNote()` — `text()` (volitelné).

### 3.5 Backend — service

**Nový soubor** `app/Services/InventoryCountService.php`:

- Konstanta `public const int CONSUMPTION_WINDOW_DAYS = 30`.

- `recordCounts(User $user, Store $store, array $rows): void` —

    - Normalizuje vstup (item_id, quantity, note).

    - V `DB::transaction`:

        - Pro každou řadu: `InventoryCount::create([...])`.

        - Upsert `store_items`: `updateOrCreate(['store_id', 'item_id'], ['quantity' => $row['quantity']])`.

- `latestCountForItem(Store $store, Item $item): InventoryCount|null`.

- `currentQuantity(Store $store, Item $item): int` — bere `store_items.getQuantity()`, fallback 0.

- `consumptionLastDays(Store $store, Item $item, int $days = self::CONSUMPTION_WINDOW_DAYS): array{quantity: int, per_day: float}` —

    - Suma `quantity_difference` (záporných) z `stock_movement_items` + `stock_movements` kde `source_store_id = $store->id`, `type = OUTGOING`, `created_at >= now()->subDays($days)`.

    - `per_day = quantity / $days` (pokud `quantity > 0`).

- `predictedRunOut(Store $store, Item $item): array{current: int, per_day: float, days_left: int|null, status: string}` —

    - `status` ∈ `'ok' | 'soon' | 'out' | 'no_data'` podle prahů:

        - `out` = `current == 0`

        - `soon` = `days_left !== null && days_left <= 7`

        - `ok` = `days_left === null || days_left > 7`

        - `no_data` = `per_day == 0`

- `buildStoreView(User $user, Store $store): array` — vrátí pole `{item_id, title, sku, unit, current, latest_count_at, avg_daily_consumption, days_until_restock, status}` pro všechny položky v katalogu uživatele, setříděné podle `status` a `days_until_restock`.

### 3.6 Backend — kontrolery

**Nový soubor** `app/Http/Controllers/Web/InventoryCount/InventoryCountIndexController.php`:

- `public const int TAKE = 1000;` (katalog mívá typicky desítky/stovky položek, chceme všechny najednou).

- Vstup: `?store_id` (povinné výběrem, default první non-warehouse store; fallback `null` => empty state).

- Načte `User::mustAuth()`, všechny `Store` uživatele (`querySelect`, `orderBy('name')`).

- Pokud je `store` vybraný, zavolá `InventoryCountService::buildStoreView($user, $store)`.

- `Inertia::render('inventory-counts/Index', [...])` — props: `store`, `stores`, `rows`, `filters`.

**Nový soubor** `app/Http/Controllers/Web/InventoryCount/InventoryCountUpdateController.php`:

- `__invoke(Request $request, InventoryCountService $service)`:

    - `ValidatesWebRequests` trait.

    - Validace: `store_id` (exists stores, owner), `rows` (array min 1), `rows.*.item_id`, `rows.*.quantity`.

    - Najde Store přes `Store::scopeForUser`.

    - Zavolá `$service->recordCounts($user, $store, $rows)`.

    - `Inertia::flash('success', __('inventory_counts.flash.saved'))`.

    - Redirect na `inventory-counts.index` se zachovaným `store_id`.

**Nový soubor** `app/Http/Controllers/Web/Report/StatisticsController.php`:

- Vstup: `?store_id`, `?period_days` (default 30).

- Data:

    - **Prodej**: agregát `StatementDay` přes `Statement` uživatele s `store_id` (za `period_days`, tj. od `now()->subDays($period_days)`); celkový `total_revenue`, breakdown kanálů.

    - **Příjem**: `StockMovement` `INCOMING` s `store_id = $store->id` za období — `total_quantity`, `total_value`, `movements_count`.

    - **Výdej/spotřeba**: `StockMovement` `OUTGOING` s `source_store_id = $store->id` — totéž.

    - **Aktuální zásoby**: `store_items` řady pro pobočku + `sum(quantity * purchase_price)`.

    - **Top spotřebované položky**: agregát přes `stock_movement_items` za období (top 10 podle `ABS(quantity_difference)`).

    - **Série pro graf**: denní příjmy a výdeje za posledních `period_days` dní.

- `Inertia::render('reports/Statistics', [...])` — props: `store`, `stores`, `period_days`, `sales`, `incoming`, `outgoing`, `current_inventory`, `top_consumed`, `daily_series`, `filters`.

### 3.7 Backend — routes

**Úprava** `routes/web.php`:

Přidat do middleware groupy `EnsureInertiaUserIsAuthenticated`:

```php
// Inventory counts
$router->get('inventory-counts', InventoryCountIndexController::class)->name('inventory-counts.index');
$router->post('inventory-counts', InventoryCountUpdateController::class)->name('inventory-counts.update');

// Reports / Statistics
$router->get('reports/statistics', StatisticsController::class)->name('reports.statistics');
```

(`inventory-counts.update` je POST, nikoliv PUT, protože posíláme celou řadu řádků najednou, ne jednotlivý záznam po id.)

### 3.8 Frontend — typy a i18n

**Úprava** `resources/js/types/index.ts` — přidat typy `InventoryCountRow`, `InventoryCountFilters`, `StatisticsPayload` (mirrorují props z kontrolerů).

**Úprava** `resources/js/i18n/cs.json`, `en.json`, `sk.json` — přidat klíče:

- `nav.inventory_counts` — „Inventura“ / „Inventory counts“ / „Inventúra“.

- `nav.statistics` — „Statistika“ / „Statistics“ / „Štatistika“.

- `inventory_counts.{title, subtitle, store, select_store, columns:{item,sku,unit,current,last_count,avg_daily_consumption,days_until_restock,status}, actions:{save}, status:{ok,soon,out,no_data}, empty:{title,description}, flash:{saved}}`.

- `statistics.{title, subtitle, store, period_days, all_stores, sales:{title,total,channels}, incoming:{title,total,quantity,movements}, outgoing:{title,total,quantity,movements}, current_inventory:{title,value,items}, top_consumed:{title,subtitle}, charts:{daily,title_sales,title_incoming,title_outgoing}}`.

### 3.9 Frontend — stránka Inventury

**Nový soubor** `resources/js/pages/inventory-counts/Index.vue`:

- Vstupní props: `store` (`{id, name, …} | null`), `stores`, `rows`, `filters`.

- Select pobočky v `Card` (mirroruje `statements/Index.vue`).

- Tabulka `DataTable` se sloupci: `Položka`, `SKU`, `Jedn.`, `Aktuální množství (Input number)`, `Naposledy napočítáno`, `Prům. spotřeba / den`, `Dnů do vyprodání`, `Stav (badge)`.

- `useForm<{ rows: Array<{ item_id, quantity, note? }> }>({ rows: … })`.

- Tlačítko „Uložit inventuru“ → `form.post(route('inventory-counts.update', { store_id }))`.

- Stavový badge barevně: `ok` = zelená, `soon` = oranžová, `out` = červená, `no_data` = šedá.

- Patička s `Σ` součtem aktuálního množství a varování „Nízký stav“ pokud existují řádky se statusem `out`/`soon`.

### 3.10 Frontend — stránka Statistiky

**Nový soubor** `resources/js/pages/reports/Statistics.vue`:

- Vstupní props: `store`, `stores`, `period_days`, sales/incoming/outgoing/current_inventory/top_consumed, `daily_series`, `filters`.

- Select pobočky + `Input number` pro `period_days` (7–365) + tlačítko „Použít“ → `router.get(route('reports.statistics'), { store_id, period_days })`.

- `Card` s `MetricCard` metrikami: prodej (Kč), příjem (Kč), výdej (Kč), aktuální hodnota zásob (Kč).

- `Card` „Top spotřebované položky“ — `DataTable` (položka, množství, hodnota, počet pohybů).

- `Card` s `Chart type="line"` pro denní příjem vs. výdej.

- `Card` s `Chart type="pie"` pro kanály prodeje (reuse struktury z `reports/Index.vue`).

### 3.11 Frontend — navigace

**Úprava** `resources/js/layouts/AppLayout.vue`:

- Přidat do `navItems` za `statements` položku:

    ```ts
    { key: 'inventory_counts', href: route('inventory-counts.index'), label: t('nav.inventory_counts'), icon: ClipboardList, active: activeUrl.value.startsWith('/inventory-counts') }
    ```

- Přidat do `navItems` za `reports` položku:

    ```ts
    { key: 'statistics', href: route('reports.statistics'), label: t('nav.statistics'), icon: TrendingUp, active: activeUrl.value.startsWith('/reports/statistics') }
    ```

- Import nových ikon z `@lucide/vue` (`ClipboardList`, `TrendingUp` — `TrendingUp` už je importovaný v `reports/Index.vue`, ale ne v `AppLayout.vue`, takže přidáme).

### 3.12 Testy (Feature + Architecture)

- `tests/Feature/App/Http/Controllers/Web/InventoryCount/InventoryCountIndexControllerTest.php`:

    - guest přesměrování na login,

    - prázdný stav bez store,

    - happy path: zobrazení položek, filltrace výstupů, `TAKE` konstanta není předmětem testu.

- `tests/Feature/App/Http/Controllers/Web/InventoryCount/InventoryCountUpdateControllerTest.php`:

    - uložení řad vytvoří `inventory_counts` a přepíše `store_items.quantity`,

    - neplatné `item_id` (cizí user) → 422,

    - uložení bez `rows` → 422.

- `tests/Feature/App/Http/Controllers/Web/Report/StatisticsControllerTest.php`:

    - host s izolovaným userem, store = warehouse + retail; ověří, že v `props` jsou `sales.total`, `incoming.total`, `outgoing.total` a `top_consumed` (pokud existuje pohyb).

- `tests/Feature/App/Models/InventoryCountTest.php`:

    - scope `scopeForStore`, `scopeForItem`, `scopeSince`,

    - gettery `getQuantity`, `getCountedAt`.

- `tests/Feature/App/Services/InventoryCountServiceTest.php`:

    - `recordCounts` vytvoří snapshot + aktualizuje `store_items`,

    - `consumptionLastDays` počítá záporné `quantity_difference` jen z `OUTGOING` v okně,

    - `predictedRunOut` vrací `days_left = null` když `per_day == 0`.

- `tests/Unit/I18nParityTest.php` — neupravujeme, klíče přidáme do všech tří locale, aby test prošel.

### 3.13 Konfigurace / dokumentace

- `CHANGELOG.md` — přidat novou sekci s krátkým zápisem o Inventuře a Statistikách (držíme se stávajícího formátu „Added / Changed“).

- `docs/application_documentation.md` — přidat `/inventory-counts` a `/reports/statistics` do HTTP surfaces.

- `docs/architecture.md` — krátký odstavec o novém toku dat: InventoryCount zapisuje snapshot + aktualizuje store_items; Statistics agreguje Statement + StockMovement + InventoryCount.

## 4. Předpoklady a rozhodnutí

- **Žádný marketplace scope v Inventuře**: pracujeme s celým katalogem uživatele (filtrace „pouze položky na pobočce“ se nechá na později — většina katalogu bude na všech pobočkách).

- **Warehouse se v Inventuře zobrazuje**: pobočky + sklady (nefiltrujeme `is_warehouse`), protože i ve skladu se počítá zásoba.

- **Adjustment pohyby** (`AdjustmentReasonEnum`) se do výpočtu spotřeby **nepočítají** — jsou to manuální korekce po inventuře, ne reálná spotřeba.

- **Outgoing = „prodáno / přesunuto“** pro účely Statistiky; v popisu stránky použijeme formulaci „příjem / výdej“ (ne „prodej“), aby nedošlo k matení s Výkazy (kde prodej = tržby).

- **Period_days default = 30** z konstanty služby; UI povolí 7..365.

- **Race conditions**: zápis inventury probíhá v transakci; `store_items` updatujeme přes `lockForUpdate()` v service (mirror `StockMovementService::lockStoreItem`), aby souběžné inventury nepřepsaly stav navzájem.

- **PHPStan**: nové třídy musí projít bez baseline; všechny scopes/gettery psát explicitně, žádné `@property`.

## 5. Verifikace

Po implementaci spustíme v tomto pořadí:

1. `composer run dev` — ruční test v prohlížeči:

    - `/inventory-counts` → výběr pobočky, zadání čísel, uložení, kontrola `store_items` v DB.

    - `/reports/statistics` → výběr pobočky + period, kontrola metrik a grafů.

    - Přepnutí na jinou pobočku = jiné hodnoty, isolace userů funguje.

2. `make fix` — formatter pass.
3. `make check`:

    - `phpstan analyse` — level max, bez baseline.

    - `pest --filter=InventoryCount` — všechny nové testy zelené.

    - `pest` — regression celého test suite (DataIsolation, Architecture).

    - `npm run type-check && npm run build` — frontend build.

4. E2E: rozšířit `tests/e2e/` o inventuru a statistiky (volitelné — vyžaduje `playwright`).

## 6. Otevřené otázky

- Má mít Inventura **datum počtu** jako samostatný atribut (formulář pro „Inventura k 31.12.“), nebo vždy `now()`? Aktuální návrh = vždy `now()`. Pokud bude požadavek na historické měsíční inventury (jako u Výkazů), přidá se později parametr `as_of`.

- Má se predikce zobrazovat i pro **položky s nulovou spotřebou**? Aktuálně `status=no_data`. Pokud uživatel chce „skryt“ tyto řádky, doplní se filtr.
