# Rating docházky ve směnách – ověření

## Výsledek

Funkce včetně veřejného rozšíření odpovídá specifikaci a je ověřená
automatizovaně i smoke kontrolou přihlášené a veřejné stránky.

## Důkazy

- `AttendanceRatingServiceTest`: 5 scénářů pro toleranci, penalizace, limity,
  absence, budoucí a otevřené směny, void, více bloků a snapshot plánu.
- Controller testy potvrzují jediný `monthly_summary`, mzdu pouze pro admina,
  veřejný badge bez detailních důvodů, tenantové oddělení a stabilní token
  omezeného účtu jen pro přiřazenou prodejnu.
- Cílená regrese Směn, předvoleb a Docházky: 56 testů, 260 assertions.
- `make check`: PHPStan bez chyb, Prettier/Pint, audity, platform requirements,
  TypeScript, produkční Vite build, 20 frontend unit testů a 593 PHP testů
  (12 659 assertions).
- Browser smoke na lokální české stránce potvrdil jednu kartu „Měsíční přehled
  a kvalita docházky“, úspěšné kopírování veřejného odkazu a stejnou kartu na
  veřejném kalendáři. Lokální aktivní prodejna neobsahovala směny; vykreslení
  konkrétního skóre proto kryjí controller a doménové testy.

## Vedlejší opravy validační brány

Plný PHPStan odhalil dvě zastaralé typové konstrukce v lokálním balíčku core:
kontrolu nemožných návratových hodnot `Request::getContent()` a zbytečný null
fallback u úplné mapy PSČ. Obě byly zjednodušeny podle aktuálních typových
kontraktů, aby standardní `make check` zůstal zelený.
