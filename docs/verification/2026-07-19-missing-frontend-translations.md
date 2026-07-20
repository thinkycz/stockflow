# Frontend translation verification

## Evidence

- Static usage audit found five missing keys, all belonging to the stores list.
- All five keys were added to English, Czech and Slovak catalogs.
- Catalog-parity and static-reference regression checks pass.
- Focused i18n suite: 6 tests passed.
- TypeScript type-check and Vite production build passed.
