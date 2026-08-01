# Checklisty provozovny – Verification

## Výsledek

Implementace odpovídá schválenému plánu a všechny požadované automatické kontroly prošly.

## Ověřené oblasti

- Přesný import 53 úkolů z denního a týdenního PDF, včetně směn, dnů a pořadí.
- Idempotentní inicializace šablon a snapshotů; prázdné adminem uložené šablony se znovu nenaplní.
- Sloučení denních a týdenních úkolů, izolace existujících snapshotů a dávkové vytvoření položek.
- Retail/warehouse a active/inactive pravidla včetně založení nové provozovny.
- Admin, limited a guest oprávnění, store/company hranice, historický zápis a optimistický zámek.
- Splnění, znovuotevření, přiřazený brigádník, neměnné události, stavy směn a omluvení dne.
- Filtrování historie před stránkováním.
- Sidebar, dvě dashboardové kartičky a administrační obrazovka v reálném Chromiu.
- Shoda českých, slovenských a anglických překladových klíčů.

## Důkazy

- `make fix` – prošlo (Prettier a Pint).
- `make check` – prošlo:
    - PHPStan: 388 souborů, bez chyb.
    - Composer a npm audity: bez zranitelností.
    - TypeScript type-check: prošel.
    - Produkční Vite build: prošel.
    - Vitest: 14 souborů, 44 testů.
    - Pest: 629 testů, 15 319 assertions.
- `npm run e2e -- tests/e2e/checklists.spec.ts` – 1 Chromium scénář prošel.
