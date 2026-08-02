# Recepty a testování brigádníků

## Zdroj pravdy

- Schválený plán z 2. 8. 2026 po `grill-with-docs` sezení.
- `/Users/leo/Downloads/TEACHA-recipes.pdf` jako doslovný zdroj počátečního katalogu.

## Funkční požadavky

- Recepty jsou jeden firemní katalog nezávislý na aktivní provozovně.
- Admin spravuje kategorie, recepty, varianty a seřazené kroky; použitý recept se archivuje, nemaže.
- Omezený účet vidí aktivní recepty a může po výběru brigádníka spustit neomezený počet testů, ale nesmí měnit katalog ani procházet historii.
- Jeden test náhodně vybere jednu variantu, promíchá její kroky a po odeslání vyžaduje pro úspěch přesné pořadí.
- Výsledek ukáže procento správných pozic a správné pořadí. Admin vidí poslední výsledek každého receptu a úplnou historii brigádníka.
- Historie ukládá snapshot receptu, varianty, kroků, odpovědi, brigádníka, auditní účet a časy, takže ji pozdější editace nezmění.

## Bezpečnostní a datové invarianty

- Všechny záznamy jsou scoped na hlavního admina přes `resolveScopeUser()`.
- Vybraný brigádník musí patřit stejné firmě; přihlášený účet zůstává auditním aktérem.
- Rozpracovaný pokus smí zobrazit a odevzdat jen jeho aktér a hodnotí se výhradně proti uloženému snapshotu.
- Archivovaný recept není dostupný omezenému účtu ani pro nový test; dříve zahájený test lze dokončit.
- UI texty jsou synchronní v CS/SK/EN, obsah receptů zůstává v původním společném znění PDF.
