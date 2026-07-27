# Limited-User Inventory Draft 404 Verification

## Verified claim

A limited user can autosave rows and close an inventory draft owned by their parent/admin account when the draft belongs to their assigned store. Drafts belonging to another store remain forbidden.

## Evidence

- Regression before the fix: the limited-user row autosave returned HTTP 404 instead of 200.
- Regression after the fix: the complete row-autosave and close flow passed and redirected to the saved inventory detail.
- Assigned-store isolation: both row autosave and draft close returned HTTP 403 for another store owned by the same parent account.
- Focused inventory controller/service suite: 29 tests passed with 111 assertions before the additional isolation case.
- `make fix`: passed.
- `make check`: passed, including PHPStan, formatting, audits, frontend type-check/build/tests, and 528 PHP tests with 10,663 assertions.

## Root-cause coverage

The two draft mutation controllers now resolve the route session through `User::resolveScopeUser()` before delegating to `InventorySessionService`, matching existing limited-user statement and inventory-detail flows. The service retains the assigned-store authorization check.
