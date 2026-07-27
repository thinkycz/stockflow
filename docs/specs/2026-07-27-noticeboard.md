# Virtuální nástěnka

## Zdroj pravdy

Schválený plán z 27. 7. 2026 po `grill-with-docs` sezení. Vizuální designový
soubor nebyl dodán; vzhled proto navazuje na existující StockFlow komponenty a
design tokeny.

## Funkční požadavky

- `/dashboard` zobrazí nad současným obsahem nástěnku aktivní nebo přiřazené
  prodejny.
- Admin i omezený uživatel mohou vytvářet, upravovat a mazat všechny kartičky
  dané prodejny.
- Kartička obsahuje titulek, rich-text obsah, právě jeden štítek, pastelovou
  barvu, volitelný obrázek a volitelné datum expirace.
- Štítky: informace, důležité, úkol, událost. Barvy: žlutá, růžová, modrá,
  zelená, fialová.
- Nástěnka podporuje aktivní/expirované karty, hledání v titulku a textu,
  filtrování štítku a stránkování po 24 kartách.
- Karty jsou nejnovější první; editace pořadí nemění.
- Dlouhý obsah se v mřížce zkrátí a celý se zobrazí v detailovém modalu.
- Smazání je soft-delete. Admin má koš, obnovu a definitivní smazání; obsah
  koše se po 30 dnech automaticky odstraňuje.
- Obrázky jsou privátní a přístupné pouze uživatelům oprávněným pro danou
  prodejnu.
- Souběžná zastaralá editace nesmí přepsat novější obsah.

## Vizuální upřesnění

- Nástěnka nemá eyebrow „Sdílený prostor“ ani vnořený panel filtrů.
- `/dashboard` má jediný viditelný nadpis „Nástěnka“; původní dashboardový
  nadpis a popis se nevykreslují.
- Kartičky mají systémové zaoblení, jemný pastel, border a stín bez efektu
  papíru s ostrými rohy.
- Admin dashboard pod nástěnkou ponechává kompaktní metriky a poslední pohyby;
  detailní analytika odkazuje na `/reports/statistics`.
- Omezený uživatel má čtyři kompaktní operativní akce; směny a docházka
  zůstávají v navigaci a provozním přehledu.

## Navigace a kontext prodejny

- Nástěnka je první položka sekce „Prodejna“ pro admina i omezeného uživatele;
  route zůstává `/dashboard`.
- `AppLayout` zobrazuje neklikací pill s aktivní prodejnou na Nástěnce,
  výkazech, inventurách, reportech, statistikách, směnách a docházce včetně
  detailních podstránek.
- Omezený uživatel pill vidí také na formulářích příjmu a výdeje. Adminská
  správa položek, pohybů, prodejen, uživatelů, pracovníků a nastavení jej
  nezobrazuje.
- Bez aktivní prodejny se pill skryje. Přepínání prodejny zůstává pouze
  v sidebaru.
- Sidebar a layout používají společnou klasifikaci store-scoped tras.

## Validace a bezpečnost

- Titulek je povinný, maximálně 120 znaků.
- Rich-text HTML je povinné po převodu na čistý text a má maximálně 20 000
  vstupních znaků.
- Povolené formátování: tři velikosti textu, tučné, kurzíva, podtržení,
  odrážky, číslování, bezpečný odkaz a zrušení formátování.
- HTML se sanitizuje na serveru explicitním whitelistem; obecný atribut
  `style`, skripty a nebezpečné URL protokoly jsou zakázané.
- Obrázek může být JPEG, PNG nebo WebP, maximálně 5 MB a 6000 × 6000 px.
- Expirace nastává na konci zvoleného dne v `Europe/Prague`; databáze ukládá
  UTC timestamp.

## Mimo rozsah

Komentáře, reakce, notifikace, připínání, drag-and-drop, realtime aktualizace,
historie verzí a workflow stavu úkolu.
