# Recepty a testování brigádníků

## Zdroj pravdy

- Schválený plán z 2. 8. 2026 po `grill-with-docs` sezení.
- `/Users/leo/Downloads/TEACHA-recipes.pdf` jako doslovný zdroj počátečního katalogu.

## Funkční požadavky

- Recepty jsou jeden firemní katalog nezávislý na aktivní provozovně.
- `/recipes` je kompaktní seznam kategorií a názvů receptů; obsah varianty je až
  v detailu a indexový payload jej neposílá.
- Admin spravuje kategorie na `/recipe-categories` a recepty, varianty a jejich
  jednotnou výrobní sekvenci v editoru; použitý recept se archivuje, nemaže.
- Varianta má seřazené instrukce typu surovina nebo akce. Surovinová instrukce
  může mít množství, fallback, jednotku, název, cílovou nádobu a ikonu; akční
  instrukce má text a akční ikonu. Zdrojové znění se v UI nezobrazuje.
- Omezený účet vidí aktivní recepty a může po výběru brigádníka spustit neomezený počet testů, ale nesmí měnit katalog ani procházet historii.
- Jeden test náhodně vybere jednu variantu a promíchá celou její výrobní sekvenci.
  Po odeslání vyžaduje pro úspěch přesné pořadí.
- Výsledek ukáže procento správných pozic a správné pořadí. Admin vidí poslední výsledek každého receptu a úplnou historii brigádníka.
- Historie nových pokusů ukládá snapshot celé seřazené sekvence včetně ikon a
  strukturovaných metadat; odpovědi, brigádníka, auditní účet a časy, takže ji
  pozdější editace nezmění. Starší pokusy zůstávají přesně ve svém původním
  snapshot formátu.

## Deterministický import a převod

- Korektivní datová migrace jednorázově odstraní dosavadní kategorie a recepty
  hlavního admina a vytvoří čistý katalog z PDF už s kanonickými instrukcemi.
  Dřívější adminovy úpravy katalogu se záměrně nepřenášejí.
- Pravidla rozdělují `+`, známé jednotky a akční suffixy, například
  `100g milk + 20g sugar - stir` na dvě suroviny a krok `stir`.
- Číselná množství se ukládají jako číslo; `half`, `a few` a změněné zápisy jako
  `1,5` zachovávají přesný fallback výraz. Nejasné texty dostanou neutrální ikonu
  nebo akci a zůstanou dohledatelné ve zdrojovém znění.
- Migrace se díky Laravel migration ledgeru spustí pouze jednou. Běžný seeder je
  navíc označen firemním časovým markerem, takže ruční opakování `db:seed` nový
  katalog znovu nemaže a nepřepisuje.
- Matcha recepty odvozují cup/matcha bowl/whisk/pour, šejkrované čaje shaker a
  nalití a cloudy oddělené šlehání. Přípravy používají jen rozpoznatelný cíl.
- Classic Matcha Latte S má osm instrukcí od `Add 100 ml milk into cup` po
  `Pour into cup`.

## Bezpečnostní a datové invarianty

- Všechny záznamy jsou scoped na hlavního admina přes `resolveScopeUser()`.
- Vybraný brigádník musí patřit stejné firmě; přihlášený účet zůstává auditním aktérem.
- Rozpracovaný pokus smí zobrazit a odevzdat jen jeho aktér a hodnotí se výhradně proti uloženému snapshotu.
- Archivovaný recept není dostupný omezenému účtu ani pro nový test; dříve zahájený test lze dokončit.
- UI texty jsou synchronní v CS/SK/EN, obsah receptů zůstává v původním společném znění PDF.
- Před smazáním katalogu se historické pokusy odpojí od původních receptů; jejich
  snapshoty zůstávají beze změny a adminský přehled je k novému receptu přiřadí
  podle firemního scope a snapshotu názvu.
