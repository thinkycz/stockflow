# Produkční katalog receptů nebyl kompletně nahrazen

## Symptom

- Po produkčním deployi zůstaly některé recepty ve staré struktuře.
- Očekávání: samotný nový deploy musí kompletně nahradit celý katalog.

## Důkazy

- Migrace `2026_08_02_000005_prepare_recipe_catalog_v2_seed.php` pouze přidává
  marker a mění cizí klíč historie.
- Mazání a nové naplnění provádí až `RecipeCatalogSeeder`, registrovaný v
  `DatabaseSeeder`.
- Deploy, který spustí jen `artisan migrate`, tedy aplikuje schéma, ale data
  katalogu nezmění.
- Již aplikovaná migrace se po změně souboru na produkci znovu nespustí.

## Root cause

Datově povinný reset byl nesprávně navázán na volitelný krok `db:seed` místo na
novou jednorázovou datovou migraci.

## Oprava

- Přidat force variantu katalogového seederu, která ignoruje starý marker.
- Přidat novou následnou migraci, která tuto force variantu spustí.
- Zachovat transakční odpojení historických snapshotů a idempotenci běžného
  `db:seed`.

## Prevence

Povinné produkční datové transformace musí mít vlastní novou migraci. Seeder smí
zůstat doplňkovou cestou pro čerstvá prostředí, ne jediným deploy triggerem.

## Ověření

- Regresní test nejprve selhal na chybějící force cestě.
- Test nové migrace simuluje nastavený starý marker, přejmenovaný recept a chybějící
  recept; po `up()` ověří 8 kategorií, 49 receptů a instrukce u každé varianty.
- `make check`: PHPStan, formátování, audity, type-check, build, 44 Vitest testů
  a 664 Pest testů / 17 441 assertions prošlo.
