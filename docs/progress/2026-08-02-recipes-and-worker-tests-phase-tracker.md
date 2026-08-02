# Recepty a testování brigádníků – phase tracker

## Stav

- Aktuální fáze: dokončeno
- Celkový stav: ověřeno
- Poslední aktualizace: 2026-08-02
- Výchozí worktree: čistý, větev `main`

## Fáze 1: Doména a katalog

- Stav: done
- [x] Migrační schéma a modely
- [x] Validity a transakční služby
- [x] Jednorázový import PDF
- [x] Service a import testy
- Blokátory: žádné

## Fáze 2: HTTP a oprávnění

- Stav: done
- [x] Routy a controllery
- [x] Admin CRUD a výsledky
- [x] Role/tenancy feature testy
- Blokátory: žádné

## Fáze 3: Inertia UI

- Stav: done
- [x] Katalog a editor
- [x] Testovací workflow a výsledky
- [x] Navigace a překlady
- Blokátory: žádné

## Fáze 4: Ověření

- Stav: done
- [x] E2E a kontraktní testy
- [x] Plné repo kontroly
- [x] Verification report a readiness verdict
- Blokátory: žádné

## Rozhodnutí

- Omezený účet vybírá brigádníka bez dalšího ověření identity; audit ukládá aktéra.
- Jeden pokus testuje jednu serverem náhodně vybranou variantu bez časového limitu.
- Úspěch vyžaduje přesné pořadí; opakování je neomezené.
- Recepty jsou firemní a historické pokusy jsou snapshoty.

## Další krok

- Předat ověřenou implementaci a aplikovat databázovou migraci při nasazení.
