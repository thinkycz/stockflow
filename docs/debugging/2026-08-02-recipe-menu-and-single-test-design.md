# Nekonzistentní menu a single-recipe test

## Symptomy

- Odkaz „Upravit“ a tlačítkové akce měly rozdílnou výšku, typografii a zarovnání.
- Test šlo spustit jen z detailu jednoho receptu.
- UPPERCASE zdrojový katalog přetékal do názvů drinků v celém UI.

## Kořenová příčina

Menu kombinovalo Inertia `Link` s globálním `Button` uvnitř nativního `details`,
takže každá položka dědila jiný layout. Testovací doména měla pouze samostatný
`RecipeTestAttempt`, a proto UI ani server neuměly atomické vícereceptové zadání.
Názvy se ukládaly přímo ze zdrojového katalogu bez kanonické normalizace.

## Oprava a guardrails

- `DropdownMenu` a `DropdownMenuItem` sjednocují layout, focus, klávesnici,
  Escape, outside-click a destruktivní stav.
- `RecipeTestSession` vlastní přesně tři child pokusy a server přijímá jediný
  finální payload; správná množství se do před-submit payloadu nemapují.
- `RecipeNameNormalizer` se používá v migraci, importu i admin CRUD a historické
  odpojené výsledky se párují case-insensitive.
- UI kontrakt, service/feature testy a dva limited Playwright průchody chrání
  neúspěšnou i stoprocentně úspěšnou větev.
