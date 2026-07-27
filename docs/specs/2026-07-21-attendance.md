# Docházka brigádníků

## Cíl

Přidat k aktivní maloobchodní prodejně provozní docházku brigádníků. Admin i
omezený účet prodejny vybírají brigádníka a zaznamenávají příchod, pauzu,
návrat a odchod. Admin navíc spravuje auditované opravy, měsíční výkazy a
tiskovou HTML variantu.

## Požadavky

- Docházka je dostupná pod Směnami a pouze pro aktivní ne-skladovou prodejnu.
- Jeden brigádník smí mít nejvýše jeden otevřený pracovní blok napříč
  prodejnami a blok nejvýše jednu otevřenou pauzu.
- Povolené přechody jsou: příchod; z přítomnosti pauza nebo odchod; z pauzy
  návrat nebo odchod.
- Prodejna je obsazená, pokud je alespoň jeden brigádník přítomen a není na
  pauze. Starý otevřený blok způsobí stav Nejasný.
- Směna se automaticky páruje v okně 60 minut před začátkem až 60 minut po
  konci. Příchod bez směny vyžaduje explicitní potvrzení.
- Časy se ukládají v UTC a zobrazují i párují v Europe/Prague. Výpočty používají
  sekundy, zobrazení celé minuty a odměna se zaokrouhlí až na haléře.
- Admin může se zdůvodněním vytvořit chybějící blok, změnit blok a pauzy nebo
  blok zneplatnit; původní a nový stav zůstávají v auditu.
- Omezený účet vidí dnešní provozní timeline. Pouze admin vidí sazby, měsíční
  výkazy, opravy a tisk.
- Tisk je samostatná Inertia HTML stránka s tiskovým CSS; PDF ani CSV se
  negeneruje.

## Terminologie

- **Pracovní blok**: interval od příchodu do odchodu jednoho brigádníka.
- **Pauza**: nezapočítaný interval uvnitř pracovního bloku.
- **Obsazenost**: odvozený stav prodejny Obsazeno, Bez obsluhy nebo Nejasný.
- **Aktivní směna**: plánovaná směna odpovídající párovacímu oknu ±60 minut.
