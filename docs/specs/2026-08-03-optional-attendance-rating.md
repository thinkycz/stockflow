# Volitelné hodnocení docházky brigádníka

## Zdroj a cíl

Zdroj požadavků je uživatelem schválený plán z 3. srpna 2026. Admin může u
brigádníka vypnout hodnocení docházky, aniž by vypnul samotnou evidenci
docházky nebo její použití ve výkazech a mzdách.

## Požadavky

- Noví i existující brigádníci mají hodnocení ve výchozím stavu zapnuté.
- Admin mění nastavení **Hodnotit docházku** ve formuláři vytvoření a úpravy;
  seznam brigádníků stav pouze zobrazuje.
- Vypnutí skryje body i všechny odvozené ratingové metriky na provozní
  Docházce, interních Směnách, detailu směny, měsíčním souhrnu a veřejném
  kalendáři.
- Vypnutí nemění příchody, pauzy, odchody, korekce, posouzení odchylek,
  plánované hodiny, výkazy ani mzdy.
- Vypnutí platí nad celou historií. Po opětovném zapnutí se rating dynamicky
  obnoví z existující docházky.
- Stav vypnutého ratingu je v datovém kontraktu explicitní a neposkytuje dříve
  vypočtené skóre ani odvozené metriky.
- UI používá ikonu `CircleOff`, lokalizovaný tooltip a text pro čtečky.

## Terminologie

Používat **hodnocení docházky**. Nepoužívat **trackování docházky**, protože
evidence docházky zůstává zapnutá.

## Mimo rozsah

- Nemění se pravidla výpočtu skóre.
- Nemění se docházkové akce, reporty, mzdové výpočty ani auditní workflow.
- Nevzniká ADR; nastavení je vratné a nemění existující snapshotovou
  architekturu.
