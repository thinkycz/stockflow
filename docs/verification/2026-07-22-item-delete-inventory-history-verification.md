# Item deletion with inventory history — verification

## Claim

An admin can delete an item that appears only in inventory history without receiving a database error, while completed inventory history remains readable.

## Regression coverage

- Deletion with stock-movement history remains blocked.
- Deletion without history remains available.
- Deletion with completed inventory history hides the item and preserves the historical row and item label.
- Deletion removes the item row from an open inventory draft.

## Status

Implemented. Final project verification evidence is recorded by the successful `make check` run associated with this change.
