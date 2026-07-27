# Limited Inventory Session Items Root Cause

## Symptom

Non-admin users saw an empty item table on an inventory-history detail that they were authorized to open.

## Root Cause

`InventorySessionService::buildSessionView()` scoped catalog items to the authenticated user. Limited users do not own the catalog; their parent/admin does. Consequently, the service could load session-item records but could not match them to any catalog item and omitted every row.

## Why It Happened

The controller correctly used the resolved parent/admin for session lookup, but passed the authenticated user to the service. The service did not resolve that user's data owner before applying the item scope. The existing limited-user test asserted only HTTP success, allowing an empty result to pass unnoticed.

## Fix Chosen

Resolve the supplied user's scope owner inside `buildSessionView()` before querying catalog items. Assigned-store authorization remains enforced by `InventoryCountShowController`.

## Regression Protection

The limited-user controller test now creates an admin-owned catalog item and inventory row and asserts the returned item ID, title, and quantity.
