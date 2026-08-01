# Rating docházky ve směnách – ověření

## Výsledek

Funkce odpovídá specifikaci a je ověřená automatizovaně i smoke kontrolou
lokální stránky `/shifts`.

## Důkazy

- `AttendanceRatingServiceTest`: 5 scénářů pro toleranci, penalizace, limity,
  absence, budoucí a otevřené směny, void, více bloků a snapshot plánu.
- Controller testy potvrzují rating pro omezený účet bez finančního souhrnu a
  nepřítomnost ratingu ve veřejném kalendáři.
- `make check`: PHPStan bez chyb, Prettier/Pint, audity, platform requirements,
  TypeScript, produkční Vite build, 19 frontend unit testů a 580 PHP testů
  (11 677 assertions).
- Browser smoke na lokální české stránce potvrdil vykreslení karty „Kvalita
  docházky“, otevření detailu dne a nulové chyby v konzoli. Lokální aktivní
  prodejna neobsahovala směny; vykreslení konkrétního skóre proto kryjí
  controller a doménové testy.

## Vedlejší opravy validační brány

Plný PHPStan odhalil dvě zastaralé typové konstrukce v lokálním balíčku core:
kontrolu nemožných návratových hodnot `Request::getContent()` a zbytečný null
fallback u úplné mapy PSČ. Obě byly zjednodušeny podle aktuálních typových
kontraktů, aby standardní `make check` zůstal zelený.
