# Recepty a testování brigádníků

## Zdroj pravdy

- Schválený plán z 2. 8. 2026 po `grill-with-docs` sezení.
- `/Users/leo/Downloads/TEACHA-recipes.pdf` jako doslovný zdroj počátečního katalogu.

## Funkční požadavky

- Recepty jsou jeden firemní katalog nezávislý na aktivní provozovně.
- `/recipes` zobrazuje kategorie a všechny aktivní recepty inline; detail zůstává pro
  soustředěné zobrazení a spuštění testu.
- Admin spravuje kategorie, recepty, varianty, strukturované suroviny a seřazené
  postupové kroky; použitý recept se archivuje, nemaže.
- Varianta má suroviny jako samostatné řádky s pořadím, množstvím, jednotkou,
  přesným fallback výrazem, ikonovou skupinou a zdrojovým zněním. Postupové kroky
  mají vlastní akci, ikonu a zdrojové znění.
- Omezený účet vidí aktivní recepty a může po výběru brigádníka spustit neomezený počet testů, ale nesmí měnit katalog ani procházet historii.
- Jeden test náhodně vybere jednu variantu, suroviny zobrazí pevně a promíchá pouze
  její postupové kroky. Po odeslání vyžaduje pro úspěch přesné pořadí.
- Výsledek ukáže procento správných pozic a správné pořadí. Admin vidí poslední výsledek každého receptu a úplnou historii brigádníka.
- Historie nových pokusů ukládá snapshot celé varianty včetně surovin, množství,
  ikon a postupových kroků; odpovědi, brigádníka, auditní účet a časy, takže ji
  pozdější editace nezmění. Starší pokusy zůstávají přesně ve svém původním
  snapshot formátu.

## Deterministický import

- Import PDF zachovává zdrojový katalog a běží jednou na firmu.
- Pravidla rozdělují `+`, známé jednotky a akční suffixy, například
  `100g milk + 20g sugar - stir` na dvě suroviny a krok `stir`.
- Číselná množství se ukládají jako číslo; `half`, `a few` a změněné zápisy jako
  `1,5` zachovávají přesný fallback výraz. Nejasné texty dostanou neutrální ikonu
  nebo akci a zůstanou dohledatelné ve zdrojovém znění.
- Pokud má varianta alespoň dva explicitní postupové kroky, generické kroky
  `add` z čistě surovinových řádků se do testu nezařadí. U kratších variant se
  použijí jako deterministický fallback, aby katalog zůstal testovatelný.

## Bezpečnostní a datové invarianty

- Všechny záznamy jsou scoped na hlavního admina přes `resolveScopeUser()`.
- Vybraný brigádník musí patřit stejné firmě; přihlášený účet zůstává auditním aktérem.
- Rozpracovaný pokus smí zobrazit a odevzdat jen jeho aktér a hodnotí se výhradně proti uloženému snapshotu.
- Archivovaný recept není dostupný omezenému účtu ani pro nový test; dříve zahájený test lze dokončit.
- UI texty jsou synchronní v CS/SK/EN, obsah receptů zůstává v původním společném znění PDF.
