# Plán: Historie inventury, české datumy, sekce Uživatelé s omezenou rolí, zrušení registrace

## Shrnutí

Tento plán pokrývá čtyři propojené změny v aplikaci StockFlow:

1. **Historie inventury** — inventura zobrazí i historický vývoj zásob (karta Historie + sparkline grafy).
2. **České datumy** — všechny datumy v celé aplikaci budou v pevném českém formátu `dd.MM.yyyy` (a `dd.MM.yyyy HH:mm` pro datum+čas), bez ohledu na UI locale.
3. **Sekce Uživatelé + omezená role** — hlavní admin bude moci vytvářet další uživatele s omezenou rolí, kteří uvidí jen Výkazy a Inventuru pro jednu přiřazenou prodejnu.
4. **Zrušení registrace** — route `/register` bude odstraněn, v aplikaci bude vždy právě jeden admin (seednutý `test@test.com`).

Datový model pro omezené uživatele: **izolovaný**. Každý omezený uživatel má vlastní `user_id`, ale admin mu při vytvoření přiřadí **jednu konkrétní prodejnu** (`users.assigned_store_id`). Omezený uživatel může pracovat pouze s touto prodejnou v sekcích Výkazy a Inventura.

---

## Aktuální stav (z exploration)

| Oblast       | Současný stav                                                                                                                                                                                                                                |
| ------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Autentizace  | `database_token` guard, HTTP-only cookies, `User` model z `thinkycz/laravel-core`. `UserSeeder` seedí `test@test.com`, když users tabulka neobsahuje záznamy.                                                                                |
| Registrace   | `RegisterController` + `/register` (GET/POST) v `guest:users` skupině. Po registraci se volá `User::provisionWarehouse()`.                                                                                                                   |
| Role         | Žádná — všichni uživatelé jsou ekvivalentní vlastníci svých dat.                                                                                                                                                                             |
| Inventura    | Stránka `inventory-counts/Index.vue` zobrazuje aktuální stav; service `InventoryCountService::buildStoreView` vrací řádky s `latest_count_at`. Tabulka `inventory_counts` uchovává snapshoty s `counted_at`.                                 |
| Datumy       | Formátováno přes `Date.toLocaleDateString()` (dle locale prohlížeče) nebo `Carbon::toDateTimeString()` na back-endu.                                                                                                                         |
| Navigace     | `AppLayout.vue` natvrdo vypisuje 8 položek (Dashboard, Items, Stock movements, Stores, Statements, Inventory, Reports, Statistics) bez ohledu na oprávnění.                                                                                  |
| Architektura | `web/*/IndexController` musí mít `public const int TAKE`. i18n klíče v `resources/js/i18n/{cs,en,sk}.json` a `lang/{cs,en,sk}.json` musí být 1:1. PHPStan level: max. `routes/web.php` používá výhradně `Resolver::resolveRouteRegistrar()`. |

---

## Návrh změn

### A) Datový model — role + přiřazená prodejna

**Nová migrace** `database/migrations/2026_06_24_000002_add_role_and_store_to_users_table.php`:

- `is_admin` (`boolean`, default `false`, NOT NULL)
- `assigned_store_id` (`foreignId('stores')->nullable()->nullOnDelete()`) — NULL = admin (žádné omezení) NEBO dosud nenastavený omezený uživatel
- Index na `is_admin` (pro rychlý "existuje admin?" dotaz)
- Index na `assigned_store_id`

**`UserSeeder`**: Po existující podmínce `User::query()->getQuery()->exists()` doplnit `update(['is_admin' => true])` pro `test@test.com`.

**`User` model** (`app/Models/User.php`):

- Nové gettery: `isAdmin(): bool` (`$this->assertBool('is_admin')`), `getAssignedStoreId(): int|null` (`$this->assertNullableInt('assigned_store_id')`).
- Nová relace `assignedStore(): BelongsTo` → `Store::class`.
- Přidaná do `BelongsToUser` ne — uživatel nevlastní `assigned_store_id` sloupec.
- Scopes (statické metody): `scopeAdmin(Builder)`, `scopeLimited(Builder)`, `scopeForStore(Builder, int $storeId)` — poslední pro admin stranu (omezení podle přiřazené prodejny omezeného uživatele).
- `casts()`: přidat `'is_admin' => 'boolean'`.

**`Store` model** (`app/Models/Store.php`):

- Nová inverzní relace `assignedUser(): HasOne` (nebo `BelongsTo` přes `users.assigned_store_id`).

**`UserFactory`** (`database/factories/UserFactory.php`):

- Stav `admin()`: `['is_admin' => true]`.
- Stav `limited(Store $store)`: `['is_admin' => false, 'assigned_store_id' => $store->getKey()]`.

---

### B) Middleware — oddělení admin vs. omezený uživatel

**Nový middleware `app/Http/Middleware/EnsureUserIsAdmin.php`**:

- Čte `User::auth()`. Pokud `null` nebo `!isAdmin()`: redirect na `route('dashboard')` s Inertia flash `'Nemáte oprávnění k této sekci.'`.
- Registrace v `bootstrap/app.php` alias `'admin'`.

**Aplikace v `routes/web.php`**:

- Skupina rout `/items`, `/stock-movements`, `/stores`, `/reports`, `/users` obalená `->middleware('admin')`.
- `/statements`, `/inventory-counts`, `/dashboard`, `/settings` zůstanou přístupné pro obě role.

---

### C) Smazání registrace

- Smazat `app/Http/Controllers/Web/Auth/RegisterController.php`.
- Smazat `tests/Feature/App/Http/Controllers/Web/Auth/RegisterControllerTest.php`.
- Smazat `resources/js/pages/auth/Register.vue`.
- Smazat routy `register.show` a `register.store` z `routes/web.php` (řádky 54–55).
- Smazat i18n klíče `auth.register.*` a `auth.login.register_prompt` ze všech tří locale JSON.
- V `resources/js/pages/auth/Login.vue` odstranit blok s "Nemáte ještě účet? Registrovat se".
- Ověřit, že `UserSeeder` seedí `test@test.com` vždy při prázdné tabulce (pro nové prostředí).

---

### D) Sekce Uživatelé (admin CRUD)

**Nové routes** v `routes/web.php` (pod middleware `'admin'`):

- `GET /users` → `UserIndexController` (`name: users.index`)
- `GET /users/create` → `UserCreateController::create` (`name: users.create`)
- `POST /users` → `UserCreateController::store` (`name: users.store`)
- `GET /users/{user}/edit` → `UserEditController::edit` (`name: users.edit`, `whereNumber`)
- `PUT /users/{user}` → `UserEditController::update` (`name: users.update`, `whereNumber`)
- `DELETE /users/{user}` → `UserDestroyController` (`name: users.destroy`, `whereNumber`)

**`UserIndexController`**: Vypíše všechny uživatele adminova store-space (pouze `user_id = admin.getKey()` pro všechny entity, ale tady chceme všechny uživatele vytvořené adminem — přes `parent_user_id` NEBO sdílené `user_id`).
**Upřesnění**: Jelikož jsme zvolili **izolovaný** model, omezení uživatelé mají vlastní `user_id`. Admin potřebuje vidět i je. Dvě varianty:

**D1) Zvolená varianta (doporučeno)**: Přidat `parent_user_id` (`foreignId('users')->nullable()->constrained('users')->nullOnDelete()`) do `users` tabulky. Admin = `parent_user_id = NULL`. Omezení uživatelé mají `parent_user_id = admin.id`. Adminovské `User::scopeForAdmin(Builder)` vrací všechny uživatele s `parent_user_id = admin.id` PLUS sebe sama. Tím se nemísí datové prostory a admin stále vidí seznam.

Tato varianta přidává do migrace ještě `parent_user_id`. Aktualizace `UserSeeder` nastavit i `parent_user_id = NULL`.

**`UserValidity`** (`app/Http/Validation/UserValidity.php`):

- `email()` — `BaseValidity::email()->unique('users', 'email')` (nullable pro update)
- `password()` — pravidla z `AuthValidity`
- `passwordConfirmation()` — `confirmed:password`
- `assignedStoreId()` — `exists('stores', 'id', ['user_id', $userId])` — tedy prodejna musí patřit adminovi
- Vlastní pravidlo přes `after()`: pokud `is_admin == false`, `assigned_store_id` je povinný.

**`UserCreateController`**:

- `create()`: vrátí `Inertia::render('users/Create', ['stores' => [...admin prodejny...]])`.
- `store()`: validuje, vytvoří usera s `parent_user_id = admin.id`, `is_admin = false`, `assigned_store_id = $storeId`. Flash `'Uživatel byl vytvořen.'`. Redirect na `users.index`.

**`UserEditController`**:

- `edit($user)`: kontrola `user.parent_user_id === admin.id` (nebo je to admin sám — pro sebe může editovat jen heslo/jméno, ne roli). Vrací `Inertia::render('users/Edit', ['user' => [...], 'stores' => [...]])`.
- `update($user)`: aktualizuje email, password (volitelně), `assigned_store_id`. Pokud se jedná o admina samotného, povolí změnu jen email/heslo.

**`UserDestroyController`**:

- Přeskočí adminy (`is_admin = true`) — `abort(403)`.
- Smaže omezeného uživatele. Kvůli `cascadeOnDelete` na `inventory_counts.user_id` (a podobně na stores/items/movements) budou smazány i jeho výkazy a inventury. Toto chování **potvrdíme v UI** ("Opravdu smazat? Budou smazány i záznamy tohoto uživatele.").

**Frontend stránky**:

- `resources/js/pages/users/Index.vue` — tabulka: email, role (Admin/Omezený), prodejna, akce.
- `resources/js/pages/users/Create.vue` — formulář: email, heslo, potvrzení hesla, výběr prodejny.
- `resources/js/pages/users/Edit.vue` — formulář: email (volitelně), nové heslo (volitelně), prodejna.

**`AppLayout.vue`**: Přidat novou položku `nav.users` (ikona `Users` z lucide) pro admin roli. Filtrovat přes `computed(() => auth.user?.is_admin === true ? navItems.value : navItems.value.filter(...))`.

---

### E) Omezení v Výkazech a Inventuře pro omezené uživatele

**`StatementIndexController`**: Přidat kontrolu na začátku:

- Pokud `!user->isAdmin()`: `storeId = user->getAssignedStoreId()`. Vyhodit chybu 403 pokud `null`. V selectu natvrdo jedna prodejna (bez dropdownu).

**`StatementUpdateController`**: Validace `store_id` musí být buď adminova prodejna NEBO `user.assigned_store_id`. Service `StatementService` doplní kontrolu.

**`InventoryCountIndexController`**: Stejný princip — pokud `!isAdmin()`, vybereme `user.assigned_store_id`. Navíc **přidáme sparkline**:

- Nová metoda `InventoryCountService::sparklineForItem(Store, Item, int $days = 30): array<int, array{label: string, value: int}>` — denní agregace snapshotů za posledních N dní (prázdné dny doplnit nulami).
- Pole `sparkline` přidáme do každé řady v `buildStoreView`.

**`InventoryCountUpdateController`**: Validace `store_id` + service kontrola: pokud `!isAdmin()`, musí být `store_id == assigned_store_id`.

**`StatisticsController`**: Pokud `!isAdmin()`, vynutit `store_id = assigned_store_id` (pokud `null`, vyhodit 403).

**`InventoryCountService::recordCounts`**: Rozšířit signaturu — pokud je uživatel omezený, ověřit že `store_id == assigned_store_id`. Pokud ne, `abort(403)`.

---

### F) Historie inventury (karta Historie + sparkline)

**Nová route**: `GET /inventory-counts/history` → `InventoryCountHistoryController` (`name: inventory-counts.history`).

**`InventoryCountHistoryController`**:

- Vstup: `?store_id=&item_id=&from=&to=` (všechno volitelné, výchozí: aktuální prodejna, všechny položky, posledních 90 dní).
- Výstup: seřazené snapshoty z `inventory_counts` (desc podle `counted_at`), seskupené po dnech.
- Vrací: `store`, `stores`, `items`, `rows` (každý řádek: id, item, množství, datum, poznámka, createdBy), `filters`.

**`InventoryCountService::historyForUser(User, ?Store, ?Item, ?Carbon $since): array`**:

- Pokud `isAdmin()`: standartní `scopeForUser` (vlastní data).
- Pokud omezený: omezit na `assigned_store_id` a vyhodit 403 pokud si admin vynucuje jiný store.

**Nová stránka `resources/js/pages/inventory-counts/History.vue`**:

- Tabulka chronologicky: datum+čas (DD.MM.YYYY HH:mm), položka, množství, poznámka, vytvořil.
- Filtry: prodejna, položka, od–do.
- "Zobrazit historii konkrétní položky" — klik na řádek otevře panel s kompletní časovou osou dané položky.

**Sparkline**:

- Nový mini-komponent `resources/js/components/ui/Sparkline.vue` — čistě SVG (path), žádné závislosti na chart knihovnách. Props: `data: number[]`, `width`, `height`, `color`.
- V `Index.vue` přidat sloupec "Vývoj (30 dní)" se sparkline.

**Úprava `Index.vue`**:

- Přidat tab přepínač nad tabulkou: **Aktuální stav** | **Historie**.
- Tab Aktuální stav = současná tabulka (plus sloupec Sparkline).
- Tab Historie = embedded tabulka snapshotů + odkaz na plnou stránku `/inventory-counts/history`.

---

### G) České datumy — `dd.MM.yyyy` všude

**Nový kompozit `resources/js/composables/useCzechDate.ts`**:

```ts
export function formatCzechDate(
    value: string | Date | null | undefined,
): string;
export function formatCzechDateTime(
    value: string | Date | null | undefined,
): string;
export function formatCzechDateRange(
    from: string | Date,
    to: string | Date,
): string;
```

- Vždy pevný formát `dd.MM.yyyy` / `dd.MM.yyyy HH:mm`, nikdy `Intl.DateTimeFormat` s locale.
- Bezpečné pro null: vrátí `'—'`.

**Soubory k aktualizaci** (najít všechna `toLocaleDateString`, `toLocaleString`, `date_format` v šablonách):

- `resources/js/pages/inventory-counts/Index.vue` (`formatLastCount`)
- `resources/js/pages/inventory-counts/History.vue` (nový)
- `resources/js/pages/statements/Index.vue` (`StatementDay.date`)
- `resources/js/pages/reports/Index.vue`, `Statistics.vue`
- `resources/js/pages/dashboard/Index.vue`
- `resources/js/pages/items/Index.vue`, `Show.vue`, `Edit.vue`, `Create.vue`
- `resources/js/pages/stock-movements/Index.vue`, `Show.vue`, `Create.vue`
- `resources/js/pages/stores/Index.vue`, `Show.vue`, `Edit.vue`, `Create.vue`
- `resources/js/pages/settings/Index.vue` (pokud obsahuje datum)
- `resources/js/components/ui/*` — pokud nějaký komponent zobrazuje datum

**Backend**: Beze změn — vše posíláme jako ISO 8601 (Carbon výstup). Formátování výhradně na frontendu.

---

### H) i18n klíče (všechny 3 locale cs/en/sk + lang/\*.json)

**Přidat do `resources/js/i18n/{cs,en,sk}.json`** pod nový namespace `users.*`:

- `nav.users`, `users.title`, `users.subtitle`
- `users.columns.email`, `users.columns.role`, `users.columns.store`, `users.columns.created`
- `users.create.title`, `users.create.submit`
- `users.edit.title`, `users.edit.submit`
- `users.fields.email`, `users.fields.password`, `users.fields.password_optional`, `users.fields.password_confirmation`, `users.fields.assigned_store`, `users.fields.select_store`
- `users.role.admin`, `users.role.limited`
- `users.confirm_delete`, `users.confirm_delete_with_data`
- `users.flash.created`, `users.flash.updated`, `users.flash.deleted`, `users.flash.cannot_delete_admin`, `users.flash.cannot_modify_admin_role`
- `inventory_counts.history.title`, `inventory_counts.history.subtitle`, `inventory_counts.history.columns.counted_at`, `inventory_counts.history.columns.item`, `inventory_counts.history.columns.quantity`, `inventory_counts.history.columns.note`, `inventory_counts.history.columns.created_by`, `inventory_counts.history.tabs.current`, `inventory_counts.history.tabs.history`, `inventory_counts.history.sparkline_label`, `inventory_counts.history.empty`, `inventory_counts.history.filter.from`, `inventory_counts.history.filter.to`, `inventory_counts.history.filter.item`, `inventory_counts.history.filter.apply`
- `flash.no_permission` ("Nemáte oprávnění k této sekci.")

**Odebrat z `resources/js/i18n/{cs,en,sk}.json`**:

- `auth.register.*` (celý blok)
- `auth.login.register_prompt`

**Přidat do `lang/{cs,en,sk}.json`** (backend flash):

- "User created.", "User updated.", "User deleted.", "You cannot delete the main admin.", "You do not have permission for this section."

---

### I) Testy (nové / upravené)

**Nové**:

- `tests/Feature/App/Models/UserRoleTest.php` — `isAdmin`, `getAssignedStoreId`, `scopeAdmin`, `scopeLimited`, `scopeForAdmin` (vrací admin + jeho podřízené).
- `tests/Feature/App/Http/Middleware/EnsureUserIsAdminTest.php` — guest redirect, limited user redirect, admin OK.
- `tests/Feature/App/Http/Controllers/Web/User/UserIndexControllerTest.php` — admin vidí seznam; limited user 403; izolace.
- `tests/Feature/App/Http/Controllers/Web/User/UserCreateControllerTest.php` — validace (email unique, heslo, prodejna povinná), uložení, flash.
- `tests/Feature/App/Http/Controllers/Web/User/UserEditControllerTest.php` — update, validace, zabránění změny admin role.
- `tests/Feature/App/Http/Controllers/Web/User/UserDestroyControllerTest.php` — smazání omezeného, zabránění smazání admina, cascade.
- `tests/Feature/App/Http/Controllers/Web/InventoryCount/InventoryCountHistoryControllerTest.php` — výpis, filtry, izolace, omezení pro limited usera.
- `tests/Feature/App/Services/InventoryCountHistoryServiceTest.php` — `historyForUser`, `sparklineForItem` (densify, řazení).
- `tests/Feature/App/Http/Controllers/Web/Statement/StatementIndexControllerTest.php` — doplnit test: limited user vidí jen svou prodejnu (bez dropdownu), 403 pokud nemá assigned_store_id.

**Upravené**:

- `tests/Feature/App/Http/Controllers/Web/Auth/RegisterControllerTest.php` — **smazat celý soubor**.
- `tests/Feature/App/Http/Controllers/Web/InventoryCount/InventoryCountUpdateControllerTest.php` — přidat test: limited user nesmí zapisovat do cizí prodejny.
- `tests/Feature/App/Http/Controllers/Web/DataIsolationTest.php` — doplnit: limited user nevidí data jiného usera (ani adminova), admin vidí data omezeného (skrze parent_user_id v agregaci, NE datově — admin má vlastní data).
- `tests/Unit/I18nParityTest.php` — beze změn (klíčový test stále projde díky 1:1 přidání/odebrání ve všech locale).
- `tests/Architecture/ControllerArchitectureTest.php` — beze změn (všechny nové `*Controller` soubory končí správně).

---

### J) Dokumentace

**`docs/application_documentation.md`** — přidat sekce:

- `GET /users`, `GET/POST /users/create`, `GET/PUT /users/{id}/edit`, `DELETE /users/{id}`
- `GET /inventory-counts/history`
- Poznámka: registrace odstraněna.

**`docs/architecture.md`** — přidat sekce:

- "Role-based access control" (admin vs. limited user, parent_user_id vazba).
- "Inventory history" (snapshot tabulka, karta Historie, sparkline).
- "Date formatting" (pouze frontend přes `useCzechDate`, nikdy backend).

**`CHANGELOG.md`** — přidat nové sekce:

- "Added (users & roles)": admin CRUD, omezená role, route `/users`, middleware `admin`, izolace dat.
- "Added (inventory history)": nová stránka `/inventory-counts/history`, karta Historie, sparkline.
- "Added (dates)": české datumy v celé aplikaci.
- "Removed (auth)": `/register` route + `RegisterController`.

---

## Ověření (verification before completion)

Po dokončení implementace provést v tomto pořadí:

1. `make fix` — formátování (Pint + Prettier).
2. `make check` — PHPStan level: max, `npm run type-check`, `npm run build`, testy + architecture testy.
3. Manuální E2E (lokální `make local`):
    - Login jako `test@test.com` (admin).
    - Vytvořit nového omezeného uživatele s přiřazenou prodejnou `X`.
    - Odhlásit se, přihlásit jako omezený uživatel.
    - Ověřit: navigace obsahuje jen Nástěnka, Výkazy, Inventura, Nastavení; select prodejny je fixní na `X`.
    - Zapsat inventuru pro `X`; ověřit uložení do DB.
    - Zkusit `GET /users` → 403 redirect.
    - Zkusit `POST /inventory-counts` s `store_id = Y` (jiná prodejna) → 403.
    - Odhlásit, přihlásit jako admin, otevřít Inventuru pro `X`: vidí sparkline a historii záznamů vytvořených omezeným uživatelem.
    - Otevřít `/inventory-counts/history` → vidí všechny snapshoty.
    - Ověřit datumy: jsou ve formátu `dd.MM.yyyy` (nebo `dd.MM.yyyy HH:mm`) na Nástěnce, Výkazech, Inventuře, Statistikách, v historii pohybů u položek, v seznamech, ve formulářích.
4. CI testy (architecture + Pest + i18n parity) musí projít.
5. Ověřit, že `/register` vrací 404, že `RegisterController` soubor neexistuje, že `RegisterControllerTest` soubor neexistuje, že `Register.vue` soubor neexistuje.

---

## Souhrn rozhodnutí (decisions captured)

| Rozhodnutí                       | Volba                                                                                           |
| -------------------------------- | ----------------------------------------------------------------------------------------------- |
| Datový model omezených uživatelů | Izolovaný (vlastní `user_id`, s `parent_user_id` směrem k adminu)                               |
| Zobrazení historie inventury     | Karta Historie + sparkline (kombinovaný přístup)                                                |
| Formát datumů                    | Pevně `dd.MM.yyyy` (a `dd.MM.yyyy HH:mm`) přes `useCzechDate` kompozit, bez ohledu na UI locale |
| Default přiřazení prodejny       | Povinné pro omezené uživatele, NULL pro admin                                                   |
| Smoke test registrace            | Trvale odstraněno; admin = seednutý `test@test.com` (jeden jediný)                              |
| Middleware                       | Nový `EnsureUserIsAdmin` alias `'admin'`, registrován v `bootstrap/app.php`                     |
| Sparkline komponenta             | Čistě SVG, bez chart knihovny, interní `resources/js/components/ui/Sparkline.vue`               |

---

## Další recommended owner skill

Po schválení plánu: `docs-driven-execution` (nebo `task-decomposition-and-resume` pro rozpad na sub-tasky pro paralelní práci sub-agentů).
