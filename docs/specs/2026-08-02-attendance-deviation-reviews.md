# Posouzení odchylky docházky

## Cíl

Na adminském měsíčním reportu docházky umožnit auditovaně posoudit rozdíl
mezi hranicemi naplánované směny a skutečné docházky. Jedna směna má jedno
posouzení odvozené z prvního příchodu a posledního odchodu všech jejích
platných dokončených pracovních bloků.

## Pravidla

- Posouzení se vyžaduje, pokud je absolutní odchylka začátku nebo konce větší
  než 15 minut. Přesně 15 minut je v toleranci.
- Schválení uloží adminem zvolené čtvrthodinové hranice do směny a synchronně
  je propíše do plánovaných snapshotů napárované docházky.
- Zamítnutí směnu nemění. Obě rozhodnutí vyžadují důvod a vytvářejí neměnný
  auditní záznam.
- Rozhodnutí platí jen pro konkrétní skutečné a plánované hranice. Pozdější
  změna docházky nebo směny případnou odchylku znovu otevře.
- Schválení je blokované uzavřeným výplatním reportem. Zamítnutí zůstává
  povolené.
- Překryv jiné směny vyžaduje stejné explicitní potvrzení jako editace v
  kalendáři.

## Akceptace

- První řádek napárované směny na `/attendance/report` obsahuje akci nebo
  stavový štítek posouzení.
- Dialog ukazuje plán, přesnou docházku, zaokrouhlený návrh a povinný důvod.
- Otevřená výplatní páska po schválení používá změněnou směnu; uzavřený
  snapshot se bez znovuotevření nemění.
