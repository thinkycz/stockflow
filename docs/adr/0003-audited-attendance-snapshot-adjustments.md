# ADR 0003: Auditovaná změna snapshotu směny

## Stav

Přijato 2026-08-02.

## Kontext

Docházka při příchodu snapshotuje plánované časy, aby pozdější běžná editace
kalendáře neměnila historické hodnocení. Admin ale potřebuje schválit skutečnou
odchylku jako opravu samotného plánu a promítnout ji do otevřené výplaty.

## Rozhodnutí

Auditované schválení odchylky je jediná výjimka, která v jedné transakci změní
časy směny i plánované snapshoty všech napárovaných bloků. Záznam posouzení
uchová skutečné hranice, plán před rozhodnutím, výsledný plán, admina a důvod.
Zamítnutí snapshot nemění a uzavřený výplatní report schválení blokuje.

## Důsledky

Běžná editace kalendáře nadále historické snapshoty nemění. Rating, report
docházky, kalendář a otevřená výplata se po výslovném schválení shodnou na
novém plánu a původní rozhodovací kontext zůstane v neměnném auditu.
