# Limited-User Inventory Draft 404

## Symptom

A non-admin user could open and edit an inventory draft for their assigned store, but saving issued `PUT /inventory-counts/drafts/{id}/rows` and received HTTP 404.

## Evidence

- The route exists and is available to authenticated admin and limited users.
- Inventory drafts are owned by the parent/admin account; the limited user is recorded as the creator.
- The inventory page finds active drafts through `resolveScopeUser()`, so it correctly returns the parent-owned draft to the limited user.
- Automatic `InventorySession` route binding uses the authenticated user's own ID through `BelongsToUser`, so it cannot bind that same parent-owned draft and returns 404 before the service authorization runs.
- Inventory show and statement mutation controllers already resolve shared records through `resolveScopeUser()` and then enforce the assigned-store boundary.

## Root cause

The draft row and close controllers used automatic route-model binding even though their data ownership model allows limited users to mutate parent-owned records for exactly one assigned store.

## Fix direction

Resolve the session ID through the parent/admin scope in the two limited-user draft mutation controllers, then retain `InventorySessionService::authoriseSession()` as the assigned-store authorization boundary.
