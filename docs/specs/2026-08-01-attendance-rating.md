# Rating docházky ve směnách

## Cíl

Přihlášeným administrátorům, omezeným účtům prodejny a návštěvníkům veřejného
kalendáře zobrazit u skončených směn transparentní skóre docházky 0–100. Mzdy
zůstávají dostupné pouze administrátorovi.

## Pravidla

- Příchod a odchod mají toleranci pět minut.
- Pozdní příchod a předčasný odchod stojí dva body za započatou minutu nad
  toleranci, každý nejvýše 35 bodů.
- Povolené pauzy tvoří 10 % plánované směny, nejvýše 30 minut. Každá započatá
  minuta navíc stojí bod, nejvýše 20 bodů.
- Více než dvě pauzy stojí pět bodů za každou další pauzu, nejvýše 10 bodů.
- Skončená směna bez platné docházky je absence se skóre 0.
- Budoucí směny a směny s otevřenou docházkou jsou čekající a do průměru
  nevstupují. Zneplatněná docházka se ignoruje.
- Více bloků stejné směny se spojí; mezery mezi bloky jsou další pauzy.
- Jakmile docházka existuje, výpočet používá její snapshot plánovaných časů.
- Pásma jsou dobré 90–100, upozornění 70–89 a špatné 0–69.
- Měsíční skóre pracovníka je nevážený průměr hodnocených směn zaokrouhlený
  na celé body.

## Akceptace

- Kalendář ukazuje stav a skóre jednotlivé směny s textovým přístupným popisem.
- Detail dne vysvětlí odchylky a je pro omezený účet pouze ke čtení.
- Jeden měsíční souhrn ukazuje plánované hodiny, skóre, dobré směny, pozdní
  příchody, předčasné odchody, problémy s pauzami a absence.
- Administrátor vidí ve společném souhrnu navíc pouze celkovou mzdu.
- Veřejný kalendář ukazuje skóre směn a stejný měsíční souhrn bez mezd a bez
  detailních důvodů jednotlivé penalizace.
- Omezený účet může vytvořit nebo zkopírovat veřejný odkaz pouze své přiřazené
  prodejny.
- Výpočet je dynamický, tenantově oddělený a bez N+1 dotazů.
