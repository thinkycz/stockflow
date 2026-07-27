# Item deletion blocked by inventory history

## Symptom

Deleting an item referenced by `inventory_session_items` returned HTTP 500 with MySQL error 1451 on `inventory_session_items_item_id_foreign`.

## Evidence

- `ItemDestroyController` checked only `stock_movement_items` before issuing a hard delete.
- `inventory_session_items.item_id` intentionally uses `RESTRICT` to protect inventory history.
- A regression test reproduced the same foreign-key failure under SQLite before the fix.
- Inventory-session detail resolves historical title, SKU, and unit through the related `Item`.

## Root cause

The deletion workflow did not account for the second historical reference path. Hard-deleting the catalog row conflicted with the audit-preserving inventory foreign key.

## Fix

- Items now use soft deletion, so active catalog queries hide deleted items without removing their historical record.
- Completed inventory rows retain access to soft-deleted items.
- Editable rows in open inventory drafts are removed transactionally during item deletion, preventing a stale draft from applying stock for an archived item.
- Existing protection against deleting items with stock-movement history remains unchanged.

## Recurrence prevention

Deletion flows for audit-linked models must enumerate every historical reference path. Historical rows should be preserved; mutable draft rows should be cleaned up explicitly.
