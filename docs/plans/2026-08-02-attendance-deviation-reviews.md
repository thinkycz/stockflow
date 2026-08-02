# Posouzení odchylek docházky — implementační plán

## Fáze 1: Doména a report

- Přidat neměnný auditní model, migraci, enum a servis rozhodnutí.
- Agregovat dokončené bloky podle směny a publikovat stav odchylky v reportu.
- Pokrýt hranice, více bloků a zastarávání rozhodnutí servisními testy.

## Fáze 2: Webový tok

- Přidat admin-only endpoint, validaci a controller testy.
- Doplnit dialog, překryvové potvrzení, stavové štítky a překlady.

## Fáze 3: Integrace a dokumentace

- Ověřit přepočet otevřené výplaty a blokaci uzavřeného reportu.
- Aktualizovat architekturu, ADR a E2E pokrytí.
- Spustit `make fix` a `make check`.
