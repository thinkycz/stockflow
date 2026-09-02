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

## Deterministický kanonický katalog

- Korektivní datová migrace jednorázově odstraní dosavadní kategorie a recepty
  hlavního admina a vytvoří čistý katalog z PDF už s kanonickými instrukcemi.
  Dřívější adminovy úpravy katalogu se záměrně nepřenášejí.
- Zdroj pravdy ukládá každou surovinu a akci přímo jako strukturovanou instrukci;
  produkční seed již neparsuje složené věty. Starý parser zůstává pouze pro
  zpětnou kompatibilitu existujících ručně importovaných dat.
- Katalog obsahuje přesně 8 kategorií, 49 receptů a 184 variant. Každý nápoj má
  explicitní variantu s ledem a bez ledu; kombinované recepty rozlišují příchuť
  nebo čajový základ i velikost.
- Číselná množství používají desetinnou tečku a mezeru před jednotkou. Tekutiny
  používají `ml` nebo `L`, sypké a pevné suroviny `g` nebo `kg`; neurčitá
  množství zůstávají explicitně označená například jako `as needed`.
- Migrace se díky Laravel migration ledgeru spustí pouze jednou. Běžný seeder je
  navíc označen firemním časovým markerem, takže ruční opakování `db:seed` nový
  katalog znovu nemaže a nepřepisuje.
- Všechny matcha a hojicha přípravy s metličkou uvádějí vodu o teplotě
  `70–80 °C`; čaje, cloudy a dávkové přípravy mají explicitní nádoby, časy a akce.
- Classic Matcha Latte S s ledem má osm instrukcí od
  `Add 100 ml milk to serving cup.` po `Pour the matcha into the serving cup.`.
- Detail nápoje zobrazuje samostatnou informační kartu pro toppingy: 0–1 topping
  používá základ, 2 toppingy odečtou 5 ml a 3 toppingy 10 ml od tekutého cukru a
  ochucených sirupů s minimem 0 ml. Pyré a Salko se nikdy nesnižují. Stejná
  vypočtená data vrací `read_recipes`; karta není součástí testovací sekvence.

## Bezpečnostní a datové invarianty

- Všechny záznamy jsou scoped na hlavního admina přes `resolveScopeUser()`.
- Vybraný brigádník musí patřit stejné firmě; přihlášený účet zůstává auditním aktérem.
- Rozpracovaný pokus smí zobrazit a odevzdat jen jeho aktér a hodnotí se výhradně proti uloženému snapshotu.
- Archivovaný recept není dostupný omezenému účtu ani pro nový test; dříve zahájený test lze dokončit.
- UI texty jsou synchronní v CS/SK/EN, kanonický obsah receptů je jednotně anglicky.
- Před smazáním katalogu se historické pokusy odpojí od původních receptů; jejich
  snapshoty zůstávají beze změny a adminský přehled je k novému receptu přiřadí
  podle firemního scope a snapshotu názvu.

## Tříreceptové testovací sezení

- Nový test se spouští pouze z indexu a obsahuje přesně tři různé náhodné aktivní
  recepty; u každého se náhodně vybere použitelná varianta.
- Brigádník prochází recepty sekvenčně bez průběžného odhalení výsledků. Finální
  odeslání je atomické a uspěje jen při správném pořadí všech instrukcí a všech
  dostupných číselných množství v `g` nebo `ml`.
- Klient nikdy neobdrží správné množství ani text, ze kterého by šlo množství
  vyčíst. Desetinná čárka a tečka jsou ekvivalentní, tolerance není povolena.
- Kategorie zůstávají verzálkami; názvy receptů se ukládají v title case. Staré
  snapshoty se nemění a párují se bez ohledu na velikost písmen.
