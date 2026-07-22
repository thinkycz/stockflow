# Limited Store Incoming

## Source

User request: a non-admin account can record issue/consumption but also needs a section for receiving goods into its assigned branch.

## Requirements

- A limited user has a dedicated "Goods receipt" navigation entry.
- The receipt form records incoming stock directly into the user's assigned store.
- A limited user cannot choose or submit another store.
- Receipt rows reuse the existing item search, decimal quantity, note, value summary, stock ledger, numbering, and notification flow.
- Existing admin stock-movement flows and limited consumption remain unchanged.
- Limited users cannot backdate receipts and return to the dashboard after saving.

## Acceptance Criteria

- Opening `/stock-movements/create?mode=incoming` as a limited user renders incoming mode with only the assigned store available.
- Posting an incoming row increases stock at the assigned store and creates an admin-owned `incoming` movement attributed to the limited creator.
- Posting incoming stock to any other store is forbidden.
- Posting transfer or adjustment modes remains forbidden for limited users.
