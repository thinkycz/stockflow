# Poměrné rozdělení spropitného – verifikace

## Verdikt

Funkce je připravená k předání. Administrátor může v otevřeném měsíčním přehledu zadat celkové spropitné a systém je přesně rozdělí podle hodin k výplatě mezi brigádníky s kladným počtem hodin. Stejná doménová operace je dostupná přes schvalovanou AI akci.

## Ověřené chování

- Servis rozděluje částku poměrem payable_hours, vynechá nulové hodiny a přesně dorovná haléřový součet metodou největších zbytků.
- HTTP cesta validuje období a částku, respektuje prodejnu i uzavřený report a vrací Inertia chyby.
- Přehled výplat zobrazuje tlačítko jen u otevřeného reportu, modal s počtem zahrnutých brigádníků a hodinami a chybový stav při nulovém počtu hodin.
- Každý nenulový podíl je běžná položka typu tip, takže se projeví v přehledu, detailu i tisku a lze ji dále spravovat.
- Anglické, české a slovenské frontendové i backendové překlady mají shodné klíče.
- AI operace distribute_payroll_tips používá stejný PayrollReportService, stejnou validaci a standardní schvalovací tok.

## Čerstvé důkazy

- make fix – úspěch.
- make check – úspěch:
    - PHPStan: 610 souborů, žádná chyba.
    - Prettier a Pint: úspěch.
    - Composer a npm audit: žádné známé zranitelnosti.
    - Vue TypeScript kontrola a produkční Vite build: úspěch.
    - Frontend unit testy: 77 úspěšných.
    - PHP testy: 894 úspěšných, 51 779 assertions; jeden externí OpenRouter smoke test standardně přeskočen.
- Payroll Playwright scénář – 1 úspěšný:
    - modal zobrazil 2 zahrnuté brigádníky a 3 hodiny k výplatě,
    - částka 600 Kč se v přehledu zobrazila jako podíly 400 Kč a 200 Kč,
    - navazující úpravy, tisk, uzavření, znovuotevření a omezení role zůstaly funkční.

## Známá omezení

- Pokud je celková částka v haléřích nižší než počet zahrnutých brigádníků, některé matematické podíly jsou 0 Kč a nevytváří se pro ně prázdná databázová položka.
- Produkční nasazení ani externí OpenRouter smoke test nebyly součástí lokální verifikace.
