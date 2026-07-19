# ADR 0001: Jednotná skladová kniha

## Stav

Přijato 2026-07-19.

## Kontext

Inventura dříve přepsala `store_items.quantity`, aniž by vznikla dohledatelná
událost. Odchozí přesuny se zároveň používaly jako zástupná spotřeba, takže
přesun skladu mezi pobočkami zkresloval náklady, marži i predikci vyprodání.

## Rozhodnutí

Všechny změny zásob zůstávají v jedné skladové knize `stock_movements` a
`stock_movement_items`. Typ události je `incoming`, `transfer`, `consumption`,
`adjustment` nebo `inventory_reconciliation`.

- **Spotřeba** je úbytek, který skutečně opustil firmu běžným provozem.
- **Přesun** mění umístění zásoby, ale nemění firemní spotřebu ani její náklad.
- **Inventurní vyrovnání** vysvětluje rozdíl mezi očekávaným a napočítaným
  stavem. Záporný nevysvětlený rozdíl je standardně odhadovaná spotřeba;
  poškození, odcizení a chybějící zboží zůstávají samostatnými ztrátami.

Inventurní snapshot a jeho případné vyrovnání vznikají v jedné databázové
transakci. Automatická a migrovaná vyrovnání jsou neměnná. `store_items`
zůstává rychlým aktuálním stavem, zatímco skladová kniha je auditní a
statistický zdroj.

Predikce používá nejvýše osm posledních uzavřených intervalů, nejvýše 56 dní.
Bez alespoň sedmi pokrytých dní vrací `no_data`; riziko je hranice sedmi dní
do vyprodání.

## Důsledky

- Přesuny nikdy nevstupují do spotřeby ani odhadované marže.
- Náklad inventurně odvozené spotřeby se ve finančních reportech poměrně
  rozděluje přes pozorovaný interval.
- Množství různých jednotek se nesčítají; souhrny používají peněžní hodnotu
  nebo počet SKU.
- Historická data doplní idempotentní příkaz
  `stockflow:backfill-inventory-consumption`, nejprve s `--dry-run`.
