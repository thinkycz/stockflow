# Plan — UI quick wins (batch 2): blur, opacity, user menu, brand rename

## Summary

Four small UI/UX cleanups:

1. Delete the decorative blur orb from [AppLayout.vue](file:///Users/leo/Herd/stockflow/resources/js/layouts/AppLayout.vue#L133-L137).
2. Drop the `text-on-surface-variant/70` opacity hack at [AppSidebar.vue:296](file:///Users/leo/Herd/stockflow/resources/js/components/ui/AppSidebar.vue#L296) (the only `/70` in the codebase).
3. Refactor [DropdownMenu](file:///Users/leo/Herd/stockflow/resources/js/components/ui/DropdownMenu.vue) to support a `#trigger` slot with configurable `placement`, then wrap the sidebar user block in a `DropdownMenu` and move Settings/Logout into the menu.
4. Rename the product: `app.name` → **Teacha**, `app.tagline` → **Management System** in all three locales and in the runtime config. User's confirmation: "rename the title to Teacha and tagline to Management System".

Total estimated work: half a day. All four are isolated, no model or controller changes, no new migration. The brand rename does change the auth cookie name — see the side-effect note in §4.

---

## Current state analysis

### File: [resources/js/layouts/AppLayout.vue](file:///Users/leo/Herd/stockflow/resources/js/layouts/AppLayout.vue)

- Lines 131–137 wrap the page content in a `relative` container with an absolute blur orb (`h-[70vw] w-[70vw] bg-primary/5 blur-[100px]`). The orb is decorative noise; `AuthLayout.vue` does not use a similar pattern, so the app chrome is the only place this exists. Purpose: none. Cost: blurred background fights with white card content and is anti-slop.

### File: [resources/js/components/ui/AppSidebar.vue](file:///Users/leo/Herd/stockflow/resources/js/components/ui/AppSidebar.vue)

- Line 296 — `text-on-surface-variant/70` on the section labels (Operations, People, etc.). The `/70` is the only opacity hack in the entire codebase (verified by Grep: zero other matches for `on-surface-variant/70`, `on-surface-variant/50`, `on-surface/70`, `on-surface/50`). The next-lighter weight in the design tokens would require introducing a tertiary token, which is over-engineering for a single usage.
- Lines 323–363 — the user block (initials + email + settings icon + logout icon). The settings and logout icons render as 14px `size-14` icons in tiny pills; they visually disappear next to the email block. The block has no `role`, no `aria-haspopup`, no `aria-expanded`; it's not announced as a menu trigger to screen readers.

### File: [resources/js/components/ui/DropdownMenu.vue](file:///Users/leo/Herd/stockflow/resources/js/components/ui/DropdownMenu.vue)

- Currently hard-codes a single trigger: an `<EllipsisVertical>` icon button with `aria-haspopup="menu"` and `aria-expanded`. The panel is positioned `absolute top-full right-0 mt-2` — only one placement (down, end-aligned). Reuse beyond "ellipsis button → menu" is impossible without forking the component.
- The only call site today is [recipes/Index.vue](file:///Users/leo/Herd/stockflow/resources/js/pages/recipes/Index.vue). Adding a `#trigger` slot with the existing button as the default content is non-breaking.

### Files: [.env](file:///Users/leo/Herd/stockflow/.env) and [.env.example](file:///Users/leo/Herd/stockflow/.env.example)

- `APP_NAME="StockFlow"` on line 1 of both. This is read by [config/app.php:22](file:///Users/leo/Herd/stockflow/config/app.php#L22) (`$env->mustParseString('APP_NAME')`) and exposed to the frontend via [HandleInertiaRequests.php:43](file:///Users/leo/Herd/stockflow/app/Http/Middleware/HandleInertiaRequests.php#L43) as `app.name`. [Brand.vue:43](file:///Users/leo/Herd/stockflow/resources/js/components/ui/Brand.vue#L43) renders `{{ app.name }}` from that shared prop.

### Files: [resources/js/i18n/en.json](file:///Users/leo/Herd/stockflow/resources/js/i18n/en.json), [sk.json](file:///Users/leo/Herd/stockflow/resources/js/i18n/sk.json), [cs.json](file:///Users/leo/Herd/stockflow/resources/js/i18n/cs.json)

- Each defines both `app.name` and `app.tagline`. The frontend only reads `app.tagline` via `t('app.tagline')` in Brand.vue; `app.name` in the i18n files is currently unused (Brand uses the shared prop). For consistency, both should be renamed in all three locales.
- Current `app.tagline` values: EN=`"Inventory Management"`, SK=`"Správa zásob"`, CS=`"Správa zásob"`.

### File: [packages/thinkycz/laravel-core/src/Guards/DatabaseTokenGuard.php:37-42](file:///Users/leo/Herd/stockflow/packages/thinkycz/laravel-core/src/Guards/DatabaseTokenGuard.php#L37-L42)

- `cookieName()` uses `Config::inject()->assertString('app.name')` to compute the auth cookie name. **Side effect of the rename**: the auth cookie name changes, so every logged-in user is silently signed out once. This is a one-time inconvenience, not a bug. No DB table prefix depends on `app.name` (verified — actual table is `database_tokens` from [0001_01_01_000000_create_users_table.php:39](file:///Users/leo/Herd/stockflow/database/migrations/0001_01_01_000000_create_users_table.php#L39); the doc note about `{app_name}_{env}_database_token_users` in `docs/application_documentation.md:131` is misleading and should be fixed in the same pass).

---

## Proposed changes

### Change 1 — Delete the dashboard blur orb

**File**: [resources/js/layouts/AppLayout.vue](file:///Users/leo/Herd/stockflow/resources/js/layouts/AppLayout.vue)
**Lines to remove**: 133–137 (the `<div class="absolute inset-0 overflow-hidden">` wrapper and its child blur div).
**Why**: decorative, anti-slop, no functional value; the auth layout does not use a similar pattern.
**How**:

- Delete the absolute-positioned wrapper block at lines 133–137.
- The inner content `<div class="z-10 flex flex-1 flex-col max-w-7xl w-full mx-auto">` (currently line 139) remains as the sole child of the `relative flex flex-1 flex-col p-4 md:p-8` wrapper.
- No need to touch the `relative` parent; it can stay for the (now-empty) future use, OR remove the `relative` too for cleanliness. **Decision: remove `relative`** to avoid dead class, since no other child uses absolute positioning.
- No JS, no CSS, no other files affected.

### Change 2 — Drop the `/70` opacity in AppSidebar section labels

**File**: [resources/js/components/ui/AppSidebar.vue](file:///Users/leo/Herd/stockflow/resources/js/components/ui/AppSidebar.vue)
**Line**: 296.
**Why**: single-use opacity hack. Adding a `text-on-surface-tertiary` token for one usage is over-engineering.
**How**:

- Change `text-on-surface-variant/70` to `text-on-surface-variant` on the section label `<p>`.
- No other changes.
- Note: the visual weight will increase slightly; this is intended (the section labels were nearly invisible before).

### Change 3 — Refactor DropdownMenu to support a custom trigger, then wrap the sidebar user block

This is two sub-changes. Do them in this order.

#### Change 3a — Extend [DropdownMenu](file:///Users/leo/Herd/stockflow/resources/js/components/ui/DropdownMenu.vue) with a `#trigger` slot and a `placement` prop

**Current API**:

```vue
<DropdownMenu label="Row actions">
  <DropdownMenuItem :href="...">Edit</DropdownMenuItem>
  <DropdownMenuItem @click="...">Delete</DropdownMenuItem>
</DropdownMenu>
```

**New API** (backwards compatible — existing ellipsis button becomes the default `#trigger`):

```vue
<!-- Backwards-compatible existing usage still works -->
<DropdownMenu label="Row actions">
  <DropdownMenuItem>Edit</DropdownMenuItem>
</DropdownMenu>

<!-- New: custom trigger for the sidebar user menu -->
<DropdownMenu label="User menu" placement="right-start" align="start">
  <template #trigger>
    <button class="...user-block...">...</button>
  </template>
  <DropdownMenuItem :href="route('settings.show')">
    <SettingsIcon :size="16" /> {{ t('nav.settings') }}
  </DropdownMenuItem>
  <DropdownMenuSeparator />
  <DropdownMenuItem variant="danger" @click="logout">
    <LogOut :size="16" /> {{ t('nav.logout') }}
  </DropdownMenuItem>
</DropdownMenu>
```

**Changes in [DropdownMenu.vue](file:///Users/leo/Herd/stockflow/resources/js/components/ui/DropdownMenu.vue)**:

- Add props: `placement?: 'bottom-end' | 'bottom-start' | 'top-end' | 'top-start' | 'right-start' | 'right-end' | 'left-start' | 'left-end'` (default `'bottom-end'`), `align?: 'start' | 'center' | 'end'` (default `'end'` for backwards compat).
- Add a `<slot name="trigger" />` whose default content is the current `<EllipsisVertical>` button.
- Compute the panel's class list from `placement + align`:
    - `bottom-*` → `top-full mt-2 left-{start:0,end:right-0,center:left-1/2 -translate-x-1/2}`
    - `top-*` → `bottom-full mb-2 ...`
    - `right-*` → `left-full ml-2 top-{start:0,end:bottom-0,center:top-1/2 -translate-y-1/2}`
    - `left-*` → `right-full mr-2 ...`
- Add `min-w-48 max-w-xs` to the panel for the user menu.
- Existing call site in [recipes/Index.vue](file:///Users/leo/Herd/stockflow/resources/js/pages/recipes/Index.vue) needs no changes.

#### Change 3b — Extend [DropdownMenuItem](file:///Users/leo/Herd/stockflow/resources/js/components/ui/DropdownMenuItem.vue) with a `variant` prop

**Why**: the Logout item should look like a destructive action (red text), matching the established pattern in Noticeboard's force-delete button (uses `variant="danger"` on `Button`).
**Changes**:

- Add `variant?: 'default' | 'danger'` (default `'default'`).
- When `variant === 'danger'`, the rendered text/hover gets `text-error-red` / `hover:bg-rose-50/50`.

#### Change 3c — Update [AppSidebar.vue](file:///Users/leo/Herd/stockflow/resources/js/components/ui/AppSidebar.vue) lines 323–363

**What**:

- Remove the `Link` (settings) and `<button>` (logout) at lines 339–362.
- Wrap the remaining user info block (initials + email) in `<DropdownMenu label="User menu" placement="right-start" align="start">` with a `#trigger` slot.
- Add a `ChevronsUpDown` (or `MoreHorizontal`) icon at the right of the user info to hint that it's clickable. Render it with `text-on-surface-variant` at 14px.
- Inside the default slot, add the two items:
    - Settings (only when `isAdmin`) — `<DropdownMenuItem :href="route('settings.show')">` with `SettingsIcon` and `t('nav.settings')`.
    - `<DropdownMenuSeparator />`.
    - Logout — `<DropdownMenuItem variant="danger" @click="logout">` with `LogOut` and `t('nav.logout')`.
- The container `<div class="flex items-center justify-between gap-2 border-t border-outline-glass pt-4 px-2">` becomes the trigger content; keep the same classes.
- Keep the `logout` function as-is (it's already a method on the component).
- The `settingsActive` computed can stay (still used for the link's active state inside the dropdown).
- Import the new components: `DropdownMenu` and `DropdownMenuItem` (already exists). `DropdownMenuSeparator` already exists.

**Why**: a single clickable affordance for the user area; visually consistent with the rest of the app's dropdown menus; settings/logout get equal treatment; both become discoverable to screen readers via `aria-haspopup` / `aria-expanded` on the new trigger.

### Change 4 — Rename to "Teacha" / "Management System"

#### Change 4a — Update runtime config

**Files**: [.env](file:///Users/leo/Herd/stockflow/.env) and [.env.example](file:///Users/leo/Herd/stockflow/.env.example)

- Change line 1 of each: `APP_NAME="StockFlow"` → `APP_NAME="Teacha"`.
- Line 18 (`MAIL_FROM_NAME="${APP_NAME}"`) follows automatically; no change needed.

**Side effect (must be documented in the plan handoff)**:

- The auth cookie name changes from `stockflow_local_database_token_users` to `teacha_local_database_token_users` (per [DatabaseTokenGuard::cookieName](file:///Users/leo/Herd/stockflow/packages/thinkycz/laravel-core/src/Guards/DatabaseTokenGuard.php#L37-L42)).
- **All logged-in users are silently logged out** on next deploy. This is acceptable for a brand rename but should be flagged.
- No DB migration needed — actual table name is `database_tokens` (verified at [0001_01_01_000000_create_users_table.php:39](file:///Users/leo/Herd/stockflow/database/migrations/0001_01_01_000000_create_users_table.php#L39)).
- The misleading documentation at [docs/application_documentation.md:131-132](file:///Users/leo/Herd/stockflow/docs/application_documentation.md#L131-L132) should be corrected in the same pass — change the table name reference to `database_tokens` and `__Host-{app_name}_{env}_database_token_users` (where appropriate) to match reality. **Out of scope for the code fix but called out here so the executor doesn't ship wrong docs.**

#### Change 4b — Update i18n files

**Files**:

- [resources/js/i18n/en.json](file:///Users/leo/Herd/stockflow/resources/js/i18n/en.json)
- [resources/js/i18n/sk.json](file:///Users/leo/Herd/stockflow/resources/js/i18n/sk.json)
- [resources/js/i18n/cs.json](file:///Users/leo/Herd/stockflow/resources/js/i18n/cs.json)

**What**: change the two `app` block keys in each file:

- `app.name` → `"Teacha"` (all three locales).
- `app.tagline` → `"Management System"` (all three locales — confirmed the user wants the same wording in all three).

**Why even though i18n `app.name` is unused**: future-proofing. If anyone later changes `Brand.vue` to read from i18n instead of the shared prop, the keys stay in sync. Also avoids drift if a translator opens the file and sees the old name.

**Where exactly** (line numbers approximate — verify before editing):

- en.json: lines 3 and 4
- sk.json: lines 3 and 4
- cs.json: lines 3 and 4

Each file's structure under `app` is:

```json
"app": {
    "name": "StockFlow",
    "tagline": "Inventory Management"
}
```

Replace both values per file.

#### Change 4c — Optional: docs touch-up

**File**: [docs/application_documentation.md](file:///Users/leo/Herd/stockflow/docs/application_documentation.md), lines 131–132.
**Change**: clarify the table name reference. The actual table is `database_tokens` (not `{app_name}_{env}_database_token_users`). Replace with the real name.
**Why**: avoids future confusion about whether the rename breaks the DB.
**Scope**: small doc fix in the same PR; otherwise leave the doc alone.

---

## Assumptions & decisions

- **Decision 1 (blur)**: delete, not scope. The auth layout does not use a similar pattern and does not need it; the cost of "scoping to auth" is moving a useless element to a different file.
- **Decision 2 (opacity)**: drop, not add a token. One usage does not justify a new design token.
- **Decision 3 (DropdownMenu refactor)**: add `#trigger` slot with the current button as default content. This is backwards compatible — the only existing call site (recipes/Index) keeps working without changes.
- **Decision 3b (DropdownMenuItem variant)**: add `variant` prop rather than copy-paste the danger styles into the AppSidebar. One knob, one source of truth.
- **Decision 3c (placement)**: for the user menu use `placement="right-start"` so the panel opens to the right of the sidebar, over the main content. `right-start` aligns the top of the panel with the top of the user block.
- **Decision 4 (rename)**: confirmed by the user. Apply to runtime config + all 3 i18n files. Same wording in all three locales. Accept the auth cookie name change as a known one-time side effect.
- **Assumption**: the `app.name` shared prop value is driven by `APP_NAME` env var; the brand block uses `useSharedProps().app.name` (not i18n). Verified at [HandleInertiaRequests.php:43](file:///Users/leo/Herd/stockflow/app/Http/Middleware/HandleInertiaRequests.php#L43) and [Brand.vue:43](file:///Users/leo/Herd/stockflow/resources/js/components/ui/Brand.vue#L43).
- **Assumption**: changing `APP_NAME` does not affect DB table names. Verified — the `database_tokens` table is created at [0001_01_01_000000_create_users_table.php:39](file:///Users/leo/Herd/stockflow/database/migrations/0001_01_01_000000_create_users_table.php#L39) without an `app.name` prefix.
- **Out of scope**: the [docs/application_documentation.md:131-132](file:///Users/leo/Herd/stockflow/docs/application_documentation.md#L131-L132) table-name reference is wrong but the doc fix is optional for this task. Recommend bundling it in the same PR; if the user prefers to defer, leave a note.
- **Out of scope**: any reference to "StockFlow" in seeders, factories, test data, or the `AGENTS.md` description. None of these affect the visible brand. If any are discovered during the change, leave a note in the PR description rather than expanding scope.

---

## Verification

Run in this order. Each step is a hard gate — do not proceed if it fails.

1. **Static checks**: `make fix` then `make check`. Both must pass with zero new warnings. This covers PHPStan (level: max), Pint/Prettier formatting, frontend type-check, and frontend build.
2. **Lint-only check after i18n edits**: re-run `make check` to confirm JSON files parse.
3. **Smoke test — visible brand**: start `make local` (or run `php artisan serve` + `npm run dev`). Load any authed page. Confirm:
    - Sidebar brand shows "Teacha" (was "StockFlow").
    - Tagline below the wordmark shows "Management System" (was "Inventory Management").
    - Same on the auth login page.
    - Mobile drawer brand: same.
4. **Smoke test — user menu**: on the same page, click the user info block in the sidebar bottom. Confirm:
    - A menu opens to the right of the sidebar with two items: "Settings" (admin only) and "Logout".
    - Settings is highlighted/active when on the settings page.
    - Escape key closes the menu.
    - Click outside the menu closes it.
    - Logout item has red text on hover.
    - Pressing Tab from the trigger moves focus into the menu; pressing Tab on the last item closes the menu and returns focus to the trigger.
5. **Smoke test — blur removed**: open the dashboard. Confirm the page background is a flat `bg-surface-bg` with no purple/primary haze.
6. **Smoke test — section labels**: sidebar section headers ("OPERATIONS", "PEOPLE", etc.) are now fully readable at the standard `text-on-surface-variant` weight (slightly darker than before).
7. **Smoke test — auth after rename**: log out, log back in. Confirm session works (this is the only check needed for the cookie name change — the auth flow must still succeed). Old cookies become invalid, which is expected.
8. **Visual regression**: open the recipes index (the existing DropdownMenu call site). Confirm the row-action ellipsis menu still works exactly as before — placement, content, keyboard nav, click-outside all unchanged.
9. **Optional — accessibility quick check**: run a screen reader through the user menu trigger; confirm "menu" role and "expanded" state are announced.

If all nine pass, the task is complete.
