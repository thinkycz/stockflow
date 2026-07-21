# Item price edit null `created_at` debug journal

## Symptom

Editing an inventory item's price succeeds, but the redirect to the item detail
fails because `ItemShowController` calls the strict `getCreatedAt()` getter on a
null `created_at` value.

## Reproduction

1. Persist an item whose nullable `created_at` column is null.
2. Submit the normal item update form with a new purchase price.
3. Follow the redirect to the item detail page.

## Evidence

- The production trace ends at the item DTO's
  `$item->getCreatedAt()->toJSON()` expression.
- `ItemEditController::update()` does not modify `created_at`.
- The `items` migration uses Laravel's nullable timestamp columns.
- The item detail Vue page declares `item.created_at` but never renders it.
- Movement timestamps are separate DTO fields and are rendered in the movement
  history table.

## Working Theory

The update exposes a pre-existing nullable timestamp when Inertia follows the
redirect. The item detail response serializes an unused field through a getter
whose contract requires the timestamp to be non-null.

## Checks Run

- Traced the production stack to `ItemShowController`.
- Compared the update controller, item model, migration, and Vue prop usage.
- Searched other web controllers for strict timestamp serialization.

## Hypotheses Rejected

- The price update clears `created_at`: the update payload contains no timestamp
  field, and Eloquent only advances `updated_at` during this operation.

## Current Hypothesis

Removing the unused item-level `created_at` DTO field and its unused frontend
type will allow legacy/null-timestamp items to render without weakening the
strict model timestamp getter or changing movement-history timestamps.

## Root Cause

The item detail response included an unused `created_at` field and serialized it
through the model's non-null `getCreatedAt()` contract even though the database
column is nullable. Editing merely exposed the stale/null data by redirecting to
that response.

## Globalization Check

The same strict getter appears on rendered movement history and the user list,
where those timestamp fields are actively used. The nullable, unused contract
mismatch is local to the item-level detail DTO, so widening the getter or
removing other timestamp fields would weaken valid contracts unnecessarily.

## Fix

Removed the unused item-level `created_at` field from the controller DTO and the
matching unused TypeScript prop. Movement timestamps remain unchanged. A feature
test reproduces the complete edit-and-redirect flow with a null item timestamp.
