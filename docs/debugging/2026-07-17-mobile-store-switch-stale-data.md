# Mobile store switch stale data

> Superseded on 2026-09-01: active-store persistence now lives in the
> browser session instead of `users.active_store_id`, so different devices
> using the same account keep independent store contexts.

## Symptom

After choosing a different store from the mobile navigation, the current page
occasionally continued to render data for the previous store.

## Evidence

- The switch endpoint persists `users.active_store_id` before returning JSON.
- `StoreSwitcher.vue` followed the successful write with `router.reload()`.
- `ActiveStoreResolver` intentionally gives a `?store_id=` query override
  priority over the persisted active store.
- Inertia reloads preserve component state, so page-local state derived from
  the previous props can also survive the refresh.
- The selector was unlocked when the Axios request completed, not when the
  subsequent Inertia refresh completed.

## Root cause

The refresh reused the current URL and preserved page state. On a URL carrying
the previous `store_id`, the resolver selected that old store again. On pages
with local state derived from props, preserving the component instance could
also leave old rows visible. Unlocking the selector between the write and the
refresh left a window for overlapping switches on slower mobile connections.

## Fix

- Remove `store_id` from the refresh URL while retaining unrelated filters.
- Perform a fresh GET Inertia visit with `preserveState: false`.
- Keep the selector disabled until that visit finishes.

## Recurrence prevention

Any persisted global-context switch must clear conflicting URL overrides and
must not preserve page-local state derived from the old context.
