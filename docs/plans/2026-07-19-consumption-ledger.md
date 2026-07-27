# Consumption ledger and statistics correction

## Goal

Separate transfers from consumption, record inventory variances in the shared
stock ledger, support manual consumption, backfill safe historical intervals,
and rebuild inventory/financial statistics around the corrected semantics.

## Phases

1. Extend movement and inventory schemas, enums, models, and migrations.
2. Implement transfer, manual consumption, and inventory reconciliation flows.
3. Add safe, idempotent historical backfill tooling.
4. Rebuild dashboard, financial reports, inventory statistics, and store detail.
5. Update frontend flows, translations, documentation, and verification.

## Locked decisions

- Existing `outgoing` rows migrate to `transfer` and never count as consumption.
- Negative inventory variance defaults to estimated consumption; positive
  variance defaults to inventory correction.
- Forecasts use at most 56 covered days and warn at seven days remaining.
- Financial margin is explicitly estimated and uses consumption cost, not
  transfers.
- Limited users may create consumption only for their assigned store.
- Inventory-generated and migrated reconciliations are immutable.
