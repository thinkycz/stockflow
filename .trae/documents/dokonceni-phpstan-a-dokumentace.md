# Plán: Dokončení PHPStan chyb a dokumentace pro Historii / Uživatele / České datumy

## Shrnutí

Implementace funkcí **Historie inventury**, **České datumy**, **Sekce Uživatelé s omezenou rolí** a **Zrušení registrace** je z větší části hotová (migrace, modely, controllery, frontend, i18n, testy). `make fix` prošel úspěšně. `make check` selhává pouze na PHPStan se **4 chybami**, které mají malý a přesně definovaný fix. Zbývá rovněž doplnit dokumentaci (`CHANGELOG.md`, `docs/application_documentation.md`, `docs/architecture.md`) podle schváleného plánu.

Tento plán je čistě dokončovací — žádné nové funkce, žádné refaktory, žádné rozšiřování scope.

---

## Aktuální stav (z exploration)

### Co je hotovo (viz `historie-uzivatele-ceske-datumy-plan.md`)

- Migrace `2026_06_24_000002_add_role_and_store_to_users_table.php` (`is_admin`, `parent_user_id`, `assigned_store_id`).
- `User` model — gettery (`isAdmin`, `getAssignedStoreId`, `getParentUserId`, `getAssignedStore`), scopes (`scopeAdmin`, `scopeLimited`, `scopeForAdmin`, `scopeForAssignedStore`), relace (`assignedStore`, `subordinateUsers`), `casts()['is_admin'] = 'boolean'`, try/catch `UniqueConstraintViolationException` v `provisionWarehouse`.
- `Store` model — `assignedUser(): HasOne` relace.
- `EnsureUserIsAdmin` middleware (alias `'admin'`).
- 4 nové `User\*` controllery (Index/Create/Edit/Destroy) + `InventoryCountHistoryController`.
- `Statement`/`InventoryCount` controllery — omezení pro `!isAdmin()` (pin na `assigned_store_id`).
- `InventoryCountService` — `historyForUser`, `sparklineForItem`, `sparkline` v `buildStoreView`.
- `UserSeeder` — `test@test.com` s `is_admin=true, parent_user_id=null`.
- `UserFactory` — stavy `admin()` a `limited(Store)`.
- Routy — `/users`, `/users/create`, `/users/{user}/edit`, `/users/{user}`, `DELETE /users/{user}`, `GET /inventory-counts/history`; `/register` odstraněn.
- Frontend — `resources/js/pages/users/{Index,Create,Edit}.vue`, `resources/js/pages/inventory-counts/History.vue`, `Sparkline.vue`, `useCzechDate.ts`.
- `AppLayout.vue` — `isAdmin` branching (`adminNavItems` vs `limitedNavItems`).
- i18n — `nav.users`, `flash.no_permission`, `inventory_counts.history.*`, `users.*` ve všech třech locale (`resources/js/i18n/{cs,en,sk}.json`); backend flash klíče v `lang/{cs,en,sk}.json`.
- `make fix` prošel (Pint + Prettier).
- Testy: `UserRoleTest`, `EnsureUserIsAdminTest`, 4× User controller testy, `InventoryCountHistoryControllerTest` + doplňky v `Statement*`, `InventoryCountUpdate*`, `InventoryCountServiceTest`.

### Co zbývá

#### 1) PHPStan chyby (4)

```
- app/Http/Controllers/Web/InventoryCount/InventoryCountHistoryController.php:164
    Chyba: Only booleans are allowed in a negated boolean, App\Models\User|null given
    Příčina: `if (!$user = User::auth())` — PHPStan nevidí `!$user` jako boolean, protože
             `User::auth()` vrací `User|null`.
    Fix: Přepsat na explicitní null check.

- app/Http/Controllers/Web/User/UserIndexController.php:64
    Chyba: Cannot call method toJSON() on mixed
    Příčina: `$user->getAttribute('created_at')?->toJSON()` — `getAttribute()` vrací `mixed`,
             takže `?->toJSON()` cílí na mixed.
    Fix: Použít `$user->getCreatedAt()->toJSON()` (non-nullable Carbon z `ModelTrait`).

- app/Http/Validation/UserValidity.php:50
    Chyba: Call to an undefined method Thinkycz\LaravelCore\Validation\Validity::ignore()
           + should return Validity but returns mixed
    Příčina: `Validity` (z `thinkycz/laravel-core`) nemá metodu `ignore()`. `ignore()` je metoda
             `Illuminate\Validation\Rules\Unique`. Místo toho `Validity::unique()` přijímá
             id a idColumn přímo: `unique(string $table, string $column, mixed $id = null,
             string|null $idColumn = null, array $wheres = []): static`.
    Fix: Předat `$ignoreId` jako 3. argument `unique()`:
         `$rule->unique('users', 'email', $ignoreId)` (nebo volat `Rule::unique()`,
         ale držíme se core `Validity` API).

- app/Models/User.php:144
    Stav: import `use Illuminate\Database\UniqueConstraintViolationException;` již existuje
          (ověřeno při exploration), `make check` by tedy po opravě tří výše uvedených
          chyb měl případně ohlásit jiný stav. Tuto chybu ponecháme k ověření.
```

#### 2) Dokumentace

Sekce J ze schváleného plánu:

- `CHANGELOG.md` — přidat `### Added (users & roles)`, `### Added (inventory history)`,
  `### Added (dates)`, `### Removed (auth)`.
- `docs/application_documentation.md` — přidat routes (`/users`, `/inventory-counts/history`),
  poznamenat odebrání registrace.
- `docs/architecture.md` — přidat sekce `Role-based access control`, `Inventory history`,
  `Date formatting`.

---

## Návrh změn

### A) Opravy PHPStan chyb (4 editace, žádné nové soubory)

**A1. `app/Http/Controllers/Web/InventoryCount/InventoryCountHistoryController.php`**

V metodě `resolveDefaultStore()` na řádku ~164 nahradit:

```php
if (!$user = User::auth()) {
    return $stores[0] ?? null;
}
```

za:

```php
$user = User::auth();
if ($user === null) {
    return $stores[0] ?? null;
}
```

(Případně lze celý blok přesunout na začátek metody, ale to by vyžadovalo předat `$user` parametrem
— držíme se co nejmenší editace.)

**A2. `app/Http/Controllers/Web/User/UserIndexController.php`**

Na řádku 64 nahradit:

```php
'created_at' => $user->getAttribute('created_at')?->toJSON(),
```

za:

```php
'created_at' => $user->getCreatedAt()->toJSON(),
```

`ModelTrait::getCreatedAt()` vrací non-nullable `Carbon` (řádek 951), `toJSON()` pak vrátí string.
`User` získává tuto metodu z `BaseUser` → `Authenticatable` → `Model` (používá `ModelTrait`).

**A3. `app/Http/Validation/UserValidity.php`**

Na řádku 50 nahradit:

```php
return $ignoreId === null ? $rule->unique('users', 'email') : $rule->unique('users', 'email')->ignore($ignoreId);
```

za:

```php
return $ignoreId === null
    ? $rule->unique('users', 'email')
    : $rule->unique('users', 'email', $ignoreId);
```

`Validity::unique()` přijímá id jako 3. parametr (`vendor/thinkycz/laravel-core/src/Validation/Concerns/HasValidityDatabaseRules.php:40`),
takže není třeba `ignore()`. Žádný nový import.

**A4. `app/Models/User.php`**

Import `use Illuminate\Database\UniqueConstraintViolationException;` je již přítomen (ověřeno v
exploration na řádku 13). Po opravě A1–A3 znovu spustit `make check` (resp. `make stan`) a ověřit,
že chyba zmizela. Pokud by přetrvala, fallback: ověřit, že `composer install` nechybí a že
`phpstan.neon` má správný `level: max` a `paths:`.

### B) Dokumentace

**B1. `CHANGELOG.md`** — pod existující `### Added (inventory)` přidat nové sekce:

```markdown
### Added (users & roles)

- Role-based access control with a single main admin (`test@test.com`) plus
  isolated limited users. Limited users are pinned to exactly one store.
- `is_admin`, `parent_user_id`, `assigned_store_id` columns on `users`.
- `/users` admin CRUD (index, create, edit, destroy) under middleware
  `EnsureUserIsAdmin` (alias `admin`).
- `User` model scopes `scopeAdmin`, `scopeLimited`, `scopeForAdmin`,
  `scopeForAssignedStore`, plus relation `assignedStore` /
  `subordinateUsers` and the `isAdmin`, `getAssignedStoreId`,
  `getParentUserId` getters.
- `Statement*` and `InventoryCount*` controllers limit limited users to
  their assigned store (403 on cross-store access, no store select).
- `UserFactory` states `admin()` and `limited(Store $store)`.
- i18n keys `nav.users`, `users.*`, `flash.no_permission`,
  `flash.cannot_delete_admin`, `flash.cannot_modify_admin_role` in cs/en/sk.
- 4 user controller tests + role / middleware tests.

### Added (inventory history)

- `/inventory-counts/history` page listing all snapshots, with filters
  (store, item, from, to) — default window 90 days.
- Sparkline column on `/inventory-counts` index showing 30-day history
  per item (`Sparkline.vue`, pure SVG, no chart library).
- `InventoryCountService::historyForUser` and `::sparklineForItem`.
- `InventoryCountHistoryController` under middleware
  `EnsureInertiaUserIsAuthenticated` (accessible to both roles; limited
  user pinned to assigned store).
- i18n keys `inventory_counts.history.*` in cs/en/sk.

### Added (dates)

- All dates are now rendered in Czech `dd.MM.yyyy` (and `dd.MM.yyyy HH:mm`
  for timestamps) via the new `useCzechDate()` composable. Backend keeps
  emitting ISO 8601 strings; formatting lives on the frontend.
- `resources/js/lib/format.ts` switched to `Intl.DateTimeFormat('cs-CZ', …)`
  so the project never relies on the browser locale.

### Removed (auth)

- `/register` GET/POST routes, `RegisterController`, `Register.vue`, and
  the `RegisterControllerTest`. New accounts can only be provisioned by
  the main admin via `/users/create`. The only admin is the seeded
  `test@test.com` account.
- i18n keys `auth.register.*` and `auth.login.register_prompt` removed
  from cs/en/sk.
```

**B2. `docs/application_documentation.md`**

V sekci `## HTTP surfaces` (řádek ~34–44) rozšířit seznam routes o:

- `/users` (admin only) — `GET`, `GET /create`, `POST`, `GET /{id}/edit`, `PUT /{id}`, `DELETE /{id}`
- `/inventory-counts/history`

Poznámka: registrace odstraněna — v úvodu nahradit
`/login, /register, /forgot-password, /reset-password` za
`/login, /forgot-password, /reset-password` (bez `/register`) a doplnit
odstavec:

```markdown
Registration has been removed. The single main admin account
(`test@test.com`) is seeded by `UserSeeder` and provisions additional
limited accounts from the `/users` section. Limited users are pinned
to one store and can only see the Statements and Inventory pages.
```

**B3. `docs/architecture.md`** — přidat tři nové sekce (za `## Storage` nebo za
`## Internationalization`):

```markdown
## Role-based access control

There is exactly one **main admin** per deployment, seeded as
`test@test.com` (`is_admin = true`, `parent_user_id = null`). The admin
provisions **limited users** (`is_admin = false`, `parent_user_id = admin.id`,
`assigned_store_id = one-of-admin-stores`).

- Limited users are pinned to one store and only see Dashboard,
  Statements, Inventory, and Settings. The store select is fixed; cross-store
  access returns 403.
- All other routes (`/items`, `/stock-movements`, `/stores`, `/reports`,
  `/users`) are wrapped by the `EnsureUserIsAdmin` middleware (alias
  `admin`) which redirects to the dashboard with an Inertia flash when
  the visitor is not the main admin.
- `User::scopeForAdmin(Builder, User $admin)` returns the admin plus
  their subordinate users for listing pages.

## Inventory history

`InventoryCount` rows are an append-only audit log of physical counts.
The `/inventory-counts/history` page lists every snapshot with store /
item / date-range filters (default window 90 days) and Czech-formatted
timestamps. The `/inventory-counts` index additionally renders a
30-day sparkline (`resources/js/components/ui/Sparkline.vue`, pure SVG)
next to each catalog item, giving the operator a quick visual trend.

## Date formatting

All UI dates use the `useCzechDate()` composable
(`resources/js/composables/useCzechDate.ts`) and are rendered in
`dd.MM.yyyy` (or `dd.MM.yyyy HH:mm` for timestamps) regardless of the
active UI locale. The backend always returns ISO 8601 strings; the
frontend formats on the client. `resources/js/lib/format.ts` also uses
`Intl.DateTimeFormat('cs-CZ', …)` so legacy call sites stay consistent.
```

---

## Ověření (verification before completion)

1. `make fix` (musí projít čistě).
2. `make check` — musí projít:
    - `make stan` (PHPStan level: max, žádné nové chyby).
    - `make frontend` (`npm run type-check` + `npm run build`).
    - `make test-unit` (Vitest).
    - `make test` (Pest, architecture testy, i18n parity test).
3. Manuální kontrola:
    - `grep -r "getAttribute('created_at')" app/` → prázdné.
    - `grep -r "ignore($ignoreId)" app/Http/Validation/` → prázdné.
    - `grep -r "if (!$user = User::auth())" app/` → prázdné.
4. Dokumentační kontrola:
    - `CHANGELOG.md` obsahuje nové `### Added (users & roles)`,
      `### Added (inventory history)`, `### Added (dates)`,
      `### Removed (auth)`.
    - `docs/application_documentation.md` neobsahuje `/register` v seznamu routes.
    - `docs/architecture.md` obsahuje `## Role-based access control`,
      `## Inventory history`, `## Date formatting`.
5. E2E smoke (lokální `make local`):
    - Login `test@test.com` → vidí `/users` v menu.
    - Vytvoření omezeného uživatele → uloží se, login funguje.
    - Omezený uživatel nevidí `/users`, nevidí jiné prodejny, vidí sparkline.
    - `/inventory-counts/history` zobrazuje záznamy s českými datumy.
    - `/register` vrací 404.

---

## Souhrn rozhodnutí (decisions captured)

| Rozhodnutí                                  | Volba                                                                                                 |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Oprava `created_at` getter                  | `ModelTrait::getCreatedAt()` (non-nullable Carbon) — konzistentní s ostatními modely                  |
| Oprava `unique` + `ignore`                  | Předat id jako 3. argument `Validity::unique()` (core API), žádný `Rule::unique`                      |
| Oprava `!$user = User::auth()`              | Explicitní `if ($user === null)` blok — nejobvyklejší vzor v codebase                                 |
| Import `UniqueConstraintViolationException` | Již přítomen v `User.php:13`; ponechat beze změn a ověřit `make stan` po A1–A3                        |
| Rozsah dokumentace                          | 4 nové CHANGELOG sekce + 2 update v `application_documentation.md` + 3 nové sekce v `architecture.md` |
| Žádné nové testy                            | Opravy A1–A3 jsou v souladu s existujícími testy (které již prošly v `make fix`)                      |

---

## Další recommended owner skill

Po dokončení: `verification-before-completion` (spustit `make check` a ověřit, že všechny
kroky prošly, než oznámit hotovo).
