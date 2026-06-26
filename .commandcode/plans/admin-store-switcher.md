# Admin Store Switcher — Sidebar-Persistent Active Store

## Goal

Add a sidebar store switcher for admins. Selecting a store writes an `active_store_id` into the session; every store-aware page (`/statements`, `/inventory-counts`, `/inventory-counts/history`, `/reports`, `/reports/statistics`, `/stock-movements`) reads from that session so the choice persists across sections. Limited users keep their pinned `assigned_store_id` (unchanged behavior) and see a read-only store label instead of the switcher.

## Architecture summary

- **Persistence**: Laravel session key `active_store_id`.
- **Write path**: `POST /stores/switch` form action → validates the id is owned by the admin → writes session → `back()`.
- **Read path**: new `ResolveActiveStore` middleware (runs in web stack before `HandleInertiaRequests`) resolves the effective active store and stuffs it into `$request->attributes` + shares `active_store` + `available_stores` via Inertia.
- **Controller pattern**: a single `ActiveStoreResolver::resolve(Request $request, User $user): Store|null` helper that prefers `?store_id=` (for shared links) → session → default-store fallback. Replaces the duplicated `resolveDefaultStore` in `StatementIndexController`, `InventoryCountIndexController`, `InventoryCountHistoryController`, `StatisticsController`, `ReportController`, `StockMovementIndexController`.
- **UI**: a sidebar `<select>` rendered only for admins; the mobile top bar keeps a compact store pill.
- **Cleanup**: per-page `<Select>` widgets on store-scoped pages are removed (limited users keep their read-only label).

## Files to change

### Backend (new)

1. **`app/Http/Controllers/Web/Store/StoreSwitchController.php`** — invokes on `POST /stores/switch`. Validates `store_id` against `Store::scopeForUser($q, $user)`. Writes `session(['active_store_id' => $id])`. Redirects `back()`.

2. **`app/Http/Middleware/ResolveActiveStore.php`** — for authenticated requests: reads `session('active_store_id')`, looks up the store under the current user's scope (limited users fall back to `assigned_store_id`), writes the resolved `Store` (or null) to `$request->attributes->set('active_store', ...)`. Skipped for guests.

3. **`app/Support/ActiveStoreResolver.php`** — single static helper used by all controllers. Signature:

    ```php
    public static function resolve(Request $request, User $user): Store|null
    ```

    Logic:
    - Limited user → return their `assigned_store_id` store (forced, ignore session/query).
    - Else: prefer `$request->query('store_id')` (parse `Typer::parseNullableInt`).
    - Else: prefer `$request->attributes->get('active_store')`.
    - Else: first non-warehouse store the user owns.
    - Validate the chosen id is actually in `Store::scopeForUser` for the user; otherwise return null.

### Backend (edit)

4. **`bootstrap/app.php`** — register the new middleware as the **last append** in the web stack (so it runs before `HandleInertiaRequests` but after auth is resolved):

    ```php
    $middleware->web(append: [
        AuthShouldUseMiddleware::class,
        SetPreferredLanguageMiddleware::class,
        ResolveActiveStore::class,
        HandleInertiaRequests::class,
    ]);
    ```

5. **`routes/web.php`** — add the switch route inside the admin group:

    ```php
    $router->post('stores/switch', StoreSwitchController::class)->name('stores.switch');
    ```

6. **`app/Http/Middleware/HandleInertiaRequests.php`** — extend `share()` to expose the active store and the available stores (admin only):

    ```php
    'active_store' => fn(): array|null => $this->activeStore(),
    'available_stores' => fn(): array => $this->availableStores(),
    ```

    `activeStore()` returns `['id', 'name', 'is_warehouse']` or null. `availableStores()` returns `[['id', 'name', 'is_warehouse'], ...]` for admins, empty for limited users.

7. **`app/Http/Controllers/Web/Statement/StatementIndexController.php`** — replace the local `resolveDefaultStore` call with `ActiveStoreResolver::resolve($request, $user)`. Drop the duplicated helper method.

8. **`app/Http/Controllers/Web/InventoryCount/InventoryCountIndexController.php`** — same change as above.

9. **`app/Http/Controllers/Web/InventoryCount/InventoryCountHistoryController.php`** — same.

10. **`app/Http/Controllers/Web/Report/StatisticsController.php`** — same.

11. **`app/Http/Controllers/Web/Report/ReportController.php`** — same.

12. **`app/Http/Controllers/Web/StockMovement/StockMovementIndexController.php`** — same.

### Frontend (types)

13. **`resources/js/types/index.ts`** — extend `SharedProps`:

    ```ts
    active_store: { id: number; name: string; is_warehouse: boolean } | null;
    available_stores: Array<{ id: number; name: string; is_warehouse: boolean }>;
    ```

    Add a matching `StoreOption` interface.

14. **`resources/js/composables/useSharedProps.ts`** — expose `activeStore` and `availableStores` computed accessors.

### Frontend (components)

15. **`resources/js/components/ui/StoreSwitcher.vue`** (new) — a styled `<select>` bound to the form action `route('stores.switch')`. Uses `@inertiajs/vue3` `useForm` so it submits via `router.post` with `preserveScroll: true` and `replace: true`. Renders the current active store as the initial value. Disabled (read-only label) for limited users.

16. **`resources/js/layouts/AppLayout.vue`** — insert `<StoreSwitcher />` in the desktop sidebar **above the nav block** (between `Brand` and the `nav`). In the mobile header, render a compact pill version after the brand. Both render only when `available_stores.length > 0` (so a single-store admin still sees it; zero stores hides it cleanly).

### Frontend (pages — remove the per-page selectors)

17. **`resources/js/pages/statements/Index.vue`** — remove the `<Select v-model="storeId">` widget and the `stores` prop consumer; rely on `active_store` from shared props. Update the `router.get` call: drop `store_id` from the params (the controller now reads it from the session) — keep `year` and `month`.

18. **`resources/js/pages/inventory-counts/Index.vue`** — same removal.

19. **`resources/js/pages/inventory-counts/History.vue`** — same removal (store filter gone; item + date range stay).

20. **`resources/js/pages/reports/Index.vue`** — same removal.

21. **`resources/js/pages/reports/statistics/Index.vue`** — same removal.

22. **`resources/js/pages/stock-movements/Index.vue`** — same removal for the store filter (other filters stay).

## Verification

After implementation:

1. **Static checks** — `make check` must pass: PHPStan level max, frontend type-check, build.
2. **Manual browser flow** (admin user with 2+ stores):
    - Land on `/dashboard`. Sidebar shows the current active store.
    - Switch store via sidebar → URL stays on `/dashboard`, `active_store` updates.
    - Navigate to `/statements` → store is already the switched one.
    - Navigate to `/inventory-counts` → same.
    - Navigate to `/reports` → same.
    - Switch again from `/reports/statistics` → other store persists across navigation.
    - Open a fresh browser tab → session still holds the active store (session-based).
    - Hit `/statements?store_id=99` where `99` belongs to a different tenant → 403 (existing scoping holds).
3. **Manual browser flow** (limited user):
    - Sidebar shows a non-interactive store label (their `assigned_store_id`).
    - Trying `POST /stores/switch` with another store id → 403 or validation error.
4. **Tests** — add a feature test under `tests/Feature/Web/Store/StoreSwitchControllerTest.php`:
    - Admin switches store → session contains `active_store_id`.
    - Admin switches to a store they don't own → 403/validation.
    - Limited user switching is rejected.
    - `ResolveActiveStore` middleware sets `active_store` attribute on the request.
5. **Regression** — all existing tests still pass; specifically the controller tests that exercise `resolveDefaultStore` paths still pass because the new helper preserves the same priority rules.

## Key existing patterns reused

- `Store::scopeForUser($query, $user)` from `app/Models/Concerns/BelongsToUser.php` — every store query still goes through it.
- `Store::querySelect` — the same column projection used by every existing index.
- `Typer::parseNullableInt` — same input parsing as `StatementIndexController`.
- `User::mustAuth()`, `User::isAdmin()`, `User::getAssignedStoreId()` — existing accessors.
- `useSharedProps()` — already the single source for shared props on the frontend.
- `@inertiajs/vue3` `useForm` + `router.post` — same pattern as the existing `logout` button.
- `Resolver::resolveRouteRegistrar()` and `Resolver::resolveRedirector()` — consistent with the rest of `routes/web.php`.
