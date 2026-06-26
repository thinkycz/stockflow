# Verzování výkazů (`/statements`)

## 1. Shrnutí

Sekce `/statements` bude po každém uložení výkazu (`Uložit`) **i** po vynulování (`Vymazat`) vytvářet **snapshot** (verzi) do tabulek `statement_versions` a `statement_version_days`, které v databázi již existují. Uživatel uvidí historii verzí pro daný výkaz a v případě chyby bude moci výkaz obnovit do dřívějšího stavu jedním kliknutím. Tok je plně vlastní (nepoužívá se `Eloquent observer`) a sedí do existujícího stylu single-action controllerů.

### Klíčová rozhodnutí (potvrzeno s uživatelem)

- **Snapshot trigger**: při každém `updateDays()` i `clear()` (vynulování je taky forma uložení).
- **Rozsah historie**: vždy **pro konkrétní statement** (1 prodejna + 1 měsíc), URL `/statements/{statement}/history`.
- **Restore bezpečnost**: před obnovením se **automaticky vytvoří snapshot aktuálního stavu**, aby šlo případně vrátit zpět i samotné obnovení.

## 2. Analýza současného stavu

Prostudované soubory (klíčové reference):

- `app/Models/Statement.php` — model výkazu; již má vztahy `versions()` a `versionDays()`; scope `scopeForUser` (z `BelongsToUser`) izoluje data.
- `app/Models/StatementDay.php` — denní řádky výkazu; mirror strukturou (cash, card, wolt, bolt, bolt_cash, foodora, total).
- `app/Models/StatementVersion.php`, `app/Models/StatementVersionDay.php` — modely **již existují** + mají `HasFactory`; **chybí jim ale příslušné factories**.
- `app/Services/StatementService.php` — `findOrCreateForMonth`, `updateDays`, `clear`, `buildReport`, `buildMetrics`, `calculateInvestment`. Žádná logika verzí.
- `app/Http/Controllers/Web/Statement/{StatementIndexController,StatementUpdateController,StatementClearController}.php` — existující řídicí logika; všechny single-action.
- `app/Http/Validation/StatementValidity.php` — vzor `*Validity` pro `days`, `amount`, `dayDate`.
- `app/Http/Controllers/Web/InventoryCount/{InventoryCountIndexController,InventoryCountUpdateController,InventoryCountHistoryController,InventoryCountShowController}.php` — **vzor pro historii** (append-only `InventorySession`, žádný restore, stránka `History.vue` + `Show.vue`).
- `app/Services/InventorySessionService.php` — vzor pro `historyForUser` a `buildSessionView`.
- `database/migrations/2026_06_26_000001_create_statement_versions_table.php`, `…_000002_create_statement_version_days_table.php` — schéma **již existuje** (`user_id`, `statement_id`, `created_by`, `snapshot_at`, `note`; `version_id`, `date`, 6 kanálů + `total`).
- `database/factories/StatementVersionFactory.php`, `StatementVersionDayFactory.php` — **neexistují**, ale modely `use HasFactory` + `@use HasFactory<StatementVersionFactory>` na ně odkazují (budou potřeba pro testy).
- `resources/js/pages/inventory-counts/{Index,History,Show}.vue` — vzor pro tlačítko „Historie" v hlavičce, read-only detail s tlačítkem akce.
- `resources/js/pages/statements/Index.vue` — aktuální stránka editoru; v hlavičce chybí odkaz na historii.
- `routes/web.php` (řádky 75–77) — současné routes statements (index, update, clear).
- `resources/js/i18n/{cs,en,sk}.json` — tři jazykové mutace; test `tests/Unit/I18nParityTest.php` hlídá paritu klíčů.

Konvence, které dodržíme:

- `use` importy; `Statement::scopeForUser`, `Statement::scopeForStatement`; `DB::transaction()`; PHPStan `level: max` bez baseline; `getKey()`, `getUserId()`; `Inertia::flash`; `Resolver::resolveRedirector()->route(...)`; web index controller deklaruje `public const int TAKE`; web controly pro zápis přes trait `ValidatesWebRequests`.

## 3. Navržené změny

### 3.1 Backend — factories (chybějící)

**Nový soubor** `database/factories/StatementVersionFactory.php`:

- `extends Factory<StatementVersion>`; `definition()` nastaví `user_id` přes `static fn() => UserFactory::new()->createOne()->getKey()`, `statement_id` přes `static fn() => Statement::factory()->createOne()->getKey()`, `created_by` = `null`, `snapshot_at` = `Carbon::now()`, `note` = `null`.
- Stavy: `byUser(User)`, `forStatement(Statement)` — nastaví `user_id` i `statement_id` dle uložiště.
- Stavy: `byCreator(User)` — `created_by = $user->getKey()`.

**Nový soubor** `database/factories/StatementVersionDayFactory.php`:

- `extends Factory<StatementVersionDay>`; `definition()` nastaví `version_id` přes `static fn() => StatementVersion::factory()->createOne()->getKey()`, `date` přes `$this->faker->unique()->date('Y-m-d')`, kanály + `total` = 0.
- Stav: `forVersion(StatementVersion)` — sváže s konkrétní verzí.

### 3.2 Backend — service

**Úprava** `app/Services/StatementService.php`:

- Přidat privátní konstantu `public const int HISTORY_LIMIT = 200` (horní strop pro `historyForStatement`; odpovídá principu `inventory-counts.HistoryController::TAKE = 1000`, ale výkaz je 1/měsíc, takže 200 stačí).
- **Nová metoda** `snapshot(Statement $statement, User $user): StatementVersion`:
    - V `DB::transaction()`:
        - `StatementVersion::create(['user_id' => $statement->getUserId(), 'statement_id' => $statement->getKey(), 'created_by' => $user->getKey(), 'snapshot_at' => Carbon::now(), 'note' => null])`.
        - Načte existující `StatementDay` řádky přes `$statement->days()->orderBy('date')->get()`.
        - Pro každý den `StatementVersionDay::create([...])` s daty (`version_id`, `date`, `cash`, `card`, `wolt`, `bolt`, `bolt_cash`, `foodora`, `total`) — kopie 1:1.
        - Vrátí `$version->fresh(['days'])`.
- **Nová metoda** `restoreVersion(StatementVersion $version, User $user): void`:
    - V `DB::transaction()`:
        - Zavolá `$this->snapshot($version->getStatement(), $user)` — zálohuje aktuální stav PŘED obnovou (viz klíčové rozhodnutí).
        - Pro každý `$version->days()->orderBy('date')->get()` najde existující `StatementDay` přes `keyBy('date')` a `update([...])` (mirror logiky z `updateDays`); pokud den chybí, přeskočí.
        - Aktuální data výkazu jsou nyní rovna datům verze.
- **Nová metoda** `historyForStatement(Statement $statement, int $limit): array`:
    - Vrátí pole `[{ id, snapshot_at, note, created_by, created_by_email, day_count }, ...]`:
        - `StatementVersion::query()` + `StatementVersion::scopeForUser($q, $statement->getUserId())` + `StatementVersion::scopeForStatement($q, $statement)` + `withCount('days')` + `orderByDesc('snapshot_at')` + `orderByDesc('id')` + `take($limit)`.
        - V druhém dotazu načte `User::query()->whereIn('id', $versions->pluck('created_by')->filter()->unique()->all())->get()->keyBy('id')` — stejný vzor jako `InventorySessionService::historyForUser`.
        - Mapuje na assoc pole s `created_by_email` lookupem.
- **Úprava** `updateDays(Statement $statement, array $rows): void`:
    - Po `DB::transaction(function () use ($statement, $rows): void { ... })` (kde proběhne update) přidat na konec metody: `$this->snapshot($statement, $user)` — ALE: `updateDays` aktuálně nepřijímá `User`. **Změna signatury** na `updateDays(Statement $statement, array $rows, User $user): void`. (Žádný jiný caller v aplikaci kromě `StatementUpdateController` tuto metodu nevolá — ověřeno grepem `updateDays(`.)
    - V controlleru `StatementUpdateController::__invoke` předat `$user` (již má `$user = User::mustAuth()`).
- **Úprava** `clear(Statement $statement): void`:
    - Změna signatury na `clear(Statement $statement, User $user): void` — po `DB::transaction(...)` zavolat `$this->snapshot($statement, $user)`.
    - V `StatementClearController::__invoke` předat `$user = User::mustAuth()`.

### 3.3 Backend — validace

**Úprava** `app/Http/Validation/StatementValidity.php`:

- Přidat metodu `versionId(): Validity` — `return $this->baseValidity->id()->exists('statement_versions', 'id', ['user_id', (string) $this->userId]);`.
- Přidat metodu `note(): Validity` — `return $this->baseValidity->make()->text()->nullable();` (volitelné, pro restore UI v budoucnu; aktuálně ji restore UI nevyužívá, ale ponecháme pro paritu s inventurou a případné rozšíření).

Pozn.: `restore` controller aktuálně nepotřebuje žádný vstup kromě route parametru `{version}` (cíl verze se předá v URL, scope `BelongsToUser` ověří vlastnictví). `versionId()` je připravené pro případné rozšíření (např. ruční výběr verze z formuláře).

### 3.4 Backend — nové controllery

**Nový soubor** `app/Http/Controllers/Web/Statement/StatementHistoryController.php`:

- `public const int TAKE = 200;` (z `StatementService::HISTORY_LIMIT`).
- `__invoke(Statement $statement, StatementService $service): Response`:
    - Zahraniční `statement` je automaticky 404 přes `BelongsToUser::resolveRouteBinding()`.
    - `User::mustAuth()`; **pokud limited user** → kontrola `assigned_store_id === $statement->getStoreId()`, jinak `abort(403)`.
    - `Inertia::render('statements/History', [...])`:
        - `statement`: `{ id, store_id, store_name, year, month }` (store_name přes `$statement->getStore()->getName()`).
        - `rows`: `$service->historyForStatement($statement, self::TAKE)`.
        - `filters`: `{ store_id, year, month }`.
        - `is_admin`: `$user->isAdmin()`.

**Nový soubor** `app/Http/Controllers/Web/Statement/StatementVersionShowController.php`:

- `__invoke(StatementVersion $version, StatementService $service): Response`:
    - Zahraniční `version` je automaticky 404 přes `BelongsToUser::resolveRouteBinding()` (StatementVersion používá `BelongsToUser`).
    - `User::mustAuth()`; **pokud limited user** → `$assignedStoreId = $user->getAssignedStoreId()`; `$version->getStatement()->getStoreId()` musí odpovídat, jinak 403.
    - Sestavit `$days`: `$version->days()->orderBy('date')->get()->map(...)` na `{ date, cash, card, wolt, bolt, bolt_cash, foodora, total }` (mirror vzoru z `StatementIndexController::days`).
    - Načíst `$creator = $version->creator()->first()` (může být `null`).
    - `Inertia::render('statements/Version', [...])`:
        - `version`: `{ id, snapshot_at, note, created_by, created_by_email }`.
        - `statement`: `{ id, store_id, store_name, year, month }`.
        - `rows`: pole řádků (viz výše).
        - `is_admin`: `$user->isAdmin()`.

**Nový soubor** `app/Http/Controllers/Web/Statement/StatementVersionRestoreController.php`:

- `__invoke(StatementVersion $version, StatementService $service): RedirectResponse`:
    - Zahraniční `version` je automaticky 404 přes `BelongsToUser::resolveRouteBinding()`.
    - `User::mustAuth()`; **pokud limited user** → kontrola `assigned_store_id === $version->getStatement()->getStoreId()`, jinak 403.
    - `$service->restoreVersion($version, $user)` — vytvoří záložní snapshot + obnoví data.
    - `Inertia::flash('success', __('Statement restored from version.'))`.
    - Přesměrování na `statements.index` s `store_id`, `year`, `month` z `$version->getStatement()`.

### 3.5 Backend — routes

**Úprava** `routes/web.php`:

Přidat do middleware groupy `EnsureInertiaUserIsAuthenticated` (za stávající tři `statements.*` routy):

```php
$router->get('statements/{statement}/history', StatementHistoryController::class)
    ->whereNumber('statement')
    ->name('statements.history');
$router->get('statements/versions/{version}', StatementVersionShowController::class)
    ->whereNumber('version')
    ->name('statements.versions.show');
$router->post('statements/versions/{version}/restore', StatementVersionRestoreController::class)
    ->whereNumber('version')
    ->name('statements.versions.restore');
```

(Použití `/{version}` route name `statements.versions.*` proto, že `StatementVersion` již používá `BelongsToUser` a `whereNumber`/`resolveRouteBinding` funguje stejně jako u `Statement`.)

### 3.6 Frontend — úprava `statements/Index.vue`

- Přidat import `History` ikony z `@lucide/vue`.
- V horní hlavičce (next to `<h1>`) přidat `Link` s tlačítkem „Historie výkazů" → `route('statements.history', { statement: props.statement.id })`. Tlačítko zobrazit jen pokud `props.statement !== null` (stejný vzor jako `inventory-counts/Index.vue` řádky 136–144).
- Přidat `statementId` do `defineProps` nebo přímo skrze `props.statement.id` v `<Link>`.

### 3.7 Frontend — nová stránka `statements/History.vue`

**Nový soubor** `resources/js/pages/statements/History.vue`:

- Struktura mirroruje `inventory-counts/History.vue`:
    - Props: `statement: { id, store_id, store_name, year, month }`, `rows: Array<{ id, snapshot_at, note, created_by, created_by_email, day_count }>`, `filters: { store_id, year, month }`, `is_admin: boolean`.
    - Hlavička: title + subtitle z i18n (`statements.history.title`, `statements.history.subtitle`) + tlačítko „← Výkaz" zpět na `statements.index` se zachovanými filtry.
    - `Card` s metadaty výkazu (prodejna, měsíc) — read-only.
    - `Card` s `DataTable`:
        - Sloupce: `Datum verze` (`formatCzechDateTime`), `Počet dní`, `Poznámka`, `Vytvořil` (created_by_email), `Otevřít` (link na `statements.versions.show`).
        - `EmptyState`, pokud `props.rows.length === 0` (`statements.history.empty.title`, `…description`).
        - Patička s počtem verzí.
- Importy: `Head`, `Link`, `AppLayout`, `Card`, `DataTable`, `EmptyState`, `useBoundLocale`, `formatCzechDate`, `formatCzechDateTime`, `useRoute`, `useSharedProps`, `useI18n`.

### 3.8 Frontend — nová stránka `statements/Version.vue`

**Nový soubor** `resources/js/pages/statements/Version.vue`:

- Struktura mirroruje `inventory-counts/Show.vue` + akční tlačítko:
    - Props: `version: { id, snapshot_at, note, created_by, created_by_email }`, `statement: { id, store_id, store_name, year, month }`, `rows: Array<DayRow>`, `is_admin: boolean`.
    - Hlavička: title „Výkaz — verze #X" + datum snapshotu + prodejna + autor.
    - `Card` s `DataTable` denních řádků (mirror struktur `statements/Index.vue`, ale read-only — hodnoty zobrazené jako `formatMoney`, žádné `Input`). Sloupce: Den, Hotovost, Karta, Wolt, Bolt, Bolt hotově, Foodora, Celkem.
    - `tfoot` s `Σ` součty za kanály (jako `statements/Index.vue`).
    - Akční panel pod tabulkou:
        - Tlačítko „Obnovit výkaz z této verze" → `router.post(route('statements.versions.restore', { version: version.id }), {}, { preserveScroll: true })` s `window.confirm(t('statements.history.confirm_restore'))`.
        - (Tlačítko se zobrazí jen pro `is_admin === true` NEBO pro limited usera s přístupem k uložišti — kontrolu na frontendu vynecháme, protože 403 řeší controller.)
- Importy: `Head`, `Link`, `router`, `AppLayout`, `Card`, `DataTable`, `Button`, `ArrowLeft`, `History` (ikona), `useBoundLocale`, `formatCzechDate`, `formatCzechDateTime`, `useRoute`, `useSharedProps`, `useI18n`, `formatMoney` z `@/lib/format`.

### 3.9 i18n — klíče pro tři jazyky

Přidat do `resources/js/i18n/{cs,en,sk}.json` pod `statements`:

- `actions.history`: „Historie verzí" / „Version history" / „História verzií".
- `session.title`: „Verze výkazu" / „Statement version" / „Verzia výkazu".
- `history.title`: „Historie verzí" / „Version history" / „História verzií".
- `history.subtitle`: „Přehled všech uložených verzí tohoto výkazu. Otevřete libovolnou verzi a případně obnovte její data." / „Overview of all saved versions of this statement. Open any version and restore its data if needed." / „Prehľad všetkých uložených verzií tohto výkazu. Otvorte ľubovoľnú verziu a prípadne obnovte jej dáta."
- `history.open`: „Otevřít" / „Open" / „Otvoriť".
- `history.versions_label`: „verzí" / „versions" / „verzií".
- `history.confirm_restore`: „Opravdu chcete obnovit výkaz z této verze? Aktuální stav bude před obnovením zálohován jako nová verze." / „Restore this version? The current state will be saved as a new version first." / „Naozaj chcete obnoviť výkaz z tejto verzie? Aktuálny stav bude pred obnovením zálohovaný ako nová verzia."
- `history.restore`: „Obnovit výkaz" / „Restore statement" / „Obnoviť výkaz".
- `history.back`: „← Zpět na výkaz" / „← Back to statement" / „← Späť na výkaz".
- `history.columns.snapshot_at`: „Datum verze" / „Snapshot at" / „Dátum verzie".
- `history.columns.day_count`: „Počet dní" / „Day count" / „Počet dní".
- `history.columns.note`: „Poznámka" / „Note" / „Poznámka".
- `history.columns.created_by`: „Vytvořil" / „Created by" / „Vytvoril".
- `history.columns.open`: „" / „" / „".
- `history.empty.title`: „Zatím žádné verze" / „No versions yet" / „Zatiaľ žiadne verzie".
- `history.empty.description`: „Historie verzí se začne plnit po každém uložení výkazu." / „Version history will fill up as soon as you save a statement." / „História verzií sa začne plniť po každom uložení výkazu."
- `flash.restored`: „Výkaz byl obnoven z vybrané verze." / „Statement restored from the selected version." / „Výkaz bol obnovený z vybranej verzie."

### 3.10 Testy

**Úprava** `tests/Feature/App/Http/Controllers/Web/Statement/StatementUpdateControllerTest.php`:

- Přidat test `'update creates a version snapshot with the new amounts'`:
    - Vytvořit statement + day, PUT s upravenými hodnotami.
    - Ověřit, že existuje právě 1 `StatementVersion`, s `statement_id`, `created_by = user->id`, `snapshot_at` v posledních 5 s.
    - Ověřit, že existuje `StatementVersionDay` pro každý den vkládaného řádku s kopií hodnot.
- Přidat test `'second update creates a second snapshot, history is preserved'`:
    - 2× PUT → 2× `StatementVersion`, každý s vlastní kopií.

**Úprava** `tests/Feature/App/Http/Controllers/Web/Statement/StatementClearControllerTest.php`:

- Rozšířit existující test o ověření, že `clear` vytvořil `StatementVersion` (s nulovými hodnotami v `StatementVersionDay`).

**Nový soubor** `tests/Feature/App/Http/Controllers/Web/Statement/StatementHistoryControllerTest.php`:

- `'admin sees version history for the selected statement'`:
    - Vytvořit admin + store + statement + 2× `StatementVersion::factory()->forStatement($statement)->create()`.
    - `GET /statements/{statement}/history` → `assertOk`, `assertInertia(component: 'statements/History', rows count: 2, is_admin: true)`.
- `'limited user is pinned to their assigned store'`:
    - Admin + 2 stores; limited user s assigned_store_id = storeA; statement pro storeA; `GET /statements/{statementA}/history` (limited) → OK.
    - Statement pro storeB; `GET /statements/{statementB}/history` (limited) → 403.
- `'history rejects another users statement'`:
    - Dva izolovaní useri; statement cizího usera → 404.
- `'history respects snapshot order'`:
    - Vytvořit 3 verze; ověřit, že v `props.rows` jsou v `snapshot_at` DESC pořadí.

**Nový soubor** `tests/Feature/App/Http/Controllers/Web/Statement/StatementVersionShowControllerTest.php`:

- `'admin opens a version detail'`:
    - Vytvořit statement + 3 dny + verzi s odpovídajícími dny; `GET /statements/versions/{version}` → OK, `props.rows` má očekávané hodnoty a `props.version.id == $version->id`.
- `'show rejects another users version'`:
    - Dva izolovaní useri; cizí verze → 404.
- `'limited user cannot open version from a different store'`:
    - Admin + 2 stores; limited user s assigned_store_id = storeA; verze pro storeB → 404.

**Nový soubor** `tests/Feature/App/Http/Controllers/Web/Statement/StatementVersionRestoreControllerTest.php`:

- `'admin restores a statement from a version'`:
    - Vytvořit statement + dny s nulami + 2× save (vytvoří 2 snapshoty). Aktualizovat den na 100. Třetí save (snapshot prázdné verze).
    - `POST /statements/versions/{firstVersion}/restore` → redirect na `statements.index` se správnými filtry + flash.
    - Ověřit, že `StatementDay` nyní obsahuje data z `firstVersion` (nula).
    - Ověřit, že vznikl **další** `StatementVersion` (záloha aktuálního stavu) — celkem 4 snapshoty.
- `'restore rejects another users version'`:
    - Cizí verze → 404.
- `'limited user cannot restore version from a different store'`:
    - Verze pro jinou store → 403.

## 4. Předpoklady a rozhodnutí

- **Append-only historie**: žádné mazání verzí (kromě cascade na smazání statementu/useru); nové verze se jen přidávají. Splňuje princip auditní stopy.
- **Žádný diff viewer**: detail verze zobrazí jen kompletní data (snapshot 1:1). Vizuální diff aktuální vs. vybraná verze je mimo scope této implementace.
- **Žádné notifikace / e-maily**: restore je okamžitý a tichý (s `flash` zprávou v UI).
- **Owner scope**: `StatementVersion` je svázán s `user_id` přes `BelongsToUser`; pro limited usera scope je `parent_user_id` (admin). Stejný princip jako u `InventorySession`.
- **Atomicita**: `snapshot` i `restore` probíhají v `DB::transaction()`.
- **Formát data verze**: `formatCzechDateTime` (lokální čas), stejně jako `inventory-counts/History.vue`.
- **Flash zprávy**: `__('Statement restored from version.')` — použijeme Laravel `__()` v controlleru (mirror stávajícího `StatementClearController`), bez přidávání klíče do `lang/*.php`, protože `__('Statement saved.')` a `__('Statement cleared.')` jsou již takto.
- **Konvence `ensureCanEdit`**: pro restore controller se nepoužije; stačí scope `BelongsToUser` + kontrola `assigned_store_id` pro limited usera (mirror `InventoryCountShowController::__invoke`).
- **Testy**: využijí existující helpery `\createIsolatedUserWithWarehouse()` a `$this->inertiaHeaders()`; nové factory `StatementVersionFactory` / `StatementVersionDayFactory` budou potřeba pro většinu testů.

## 5. Verifikace

Po implementaci spustíme v tomto pořadí:

1. **PHPUnit / Pest (Feature)**:
    - `composer run test -- --filter=Statement`
    - Všechny nové + aktualizované testy zelené.
2. **PHPStan**: `vendor/bin/phpstan analyse` (přes `make check`) — level `max`, bez baseline.
3. **Prettier / Pint**: `make fix`.
4. **Frontend type-check + build**: `npm run type-check && npm run build`.
5. **i18n parity**: `composer run test -- --filter=I18nParity` (klíče přidány do všech tří locale).
6. **Architecture test**: `composer run test -- --filter=Architecture` — ověří, že `StatementHistoryController`/`StatementVersionShowController` dodržují konvence (presence `TAKE` je volitelná, ale dává smysl jen pro `HistoryController`).
7. **DataIsolation**: `composer run test -- --filter=DataIsolation` — ověří, že `StatementVersion::scopeForUser` správně izoluje verze mezi uživateli.
8. **Ruční test v prohlížeči** (`composer run dev`):
    - `/statements` → upravit den → Uložit → kliknout „Historie" → vidět 1 verzi.
    - Upravit den na jinou hodnotu → Uložit → Historie → vidět 2 verze.
    - Otevřít první verzi → kliknout „Obnovit výkaz z této verze" → potvrdit → výkaz se vrátí na starší hodnoty; v Historie přibyla nová verze (záloha).
    - Otevřít `/statements/{statement}/history` jako limited user přiřazený k jiné prodejně → 403.
    - Cizí `statement`/`version` → 404.
9. **Make check**: `make check` — kompletní pipeline (PHPStan, Prettier, Pint, audity, build, testy).

## 6. Struktura nových / upravených souborů

### Backend — nové

- `database/factories/StatementVersionFactory.php`
- `database/factories/StatementVersionDayFactory.php`
- `app/Http/Controllers/Web/Statement/StatementHistoryController.php`
- `app/Http/Controllers/Web/Statement/StatementVersionShowController.php`
- `app/Http/Controllers/Web/Statement/StatementVersionRestoreController.php`
- `tests/Feature/App/Http/Controllers/Web/Statement/StatementHistoryControllerTest.php`
- `tests/Feature/App/Http/Controllers/Web/Statement/StatementVersionShowControllerTest.php`
- `tests/Feature/App/Http/Controllers/Web/Statement/StatementVersionRestoreControllerTest.php`

### Backend — upravené

- `app/Services/StatementService.php` (snapshot + restoreVersion + historyForStatement; updateDays/clear nyní přijímají `User $user`)
- `app/Http/Validation/StatementValidity.php` (`versionId()`, `note()`)
- `app/Http/Controllers/Web/Statement/StatementUpdateController.php` (předá `$user` do `updateDays`)
- `app/Http/Controllers/Web/Statement/StatementClearController.php` (předá `$user` do `clear`)
- `routes/web.php` (3 nové routes)
- `tests/Feature/App/Http/Controllers/Web/Statement/StatementUpdateControllerTest.php` (snapshot testy)
- `tests/Feature/App/Http/Controllers/Web/Statement/StatementClearControllerTest.php` (snapshot test)

### Frontend — nové

- `resources/js/pages/statements/History.vue`
- `resources/js/pages/statements/Version.vue`

### Frontend — upravené

- `resources/js/pages/statements/Index.vue` (tlačítko „Historie")
- `resources/js/i18n/cs.json` (sekce `statements.history.*`, `statements.session.*`, `statements.flash.restored`, `statements.actions.history`)
- `resources/js/i18n/en.json` (totéž)
- `resources/js/i18n/sk.json` (totéž)
