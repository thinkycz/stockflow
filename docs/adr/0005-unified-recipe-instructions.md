# ADR 0005: Unified recipe instructions

## Status

Accepted — 2026-08-02

## Context

Oddělené suroviny a postup z ADR 0004 vedly k objemnému detailu a neuměly
vyjádřit skutečné pořadí výroby, například přidání mléka, zamíchání, přípravu
matchy v misce a následné nalití. Test navíc hodnotil jen část receptu.

## Decision

- Přidat seřazené `recipe_instructions` jako kanonický obsah varianty. Instrukce
  je surovina nebo akce a nese zobrazovaný text, ikonu a volitelná strukturovaná
  metadata množství, jednotky, suroviny a cílové nádoby.
- Legacy `recipe_ingredients` a `recipe_steps` ponechat pro jednorázový převod a
  kompatibilitu, ale nové editace zapisují pouze kanonické instrukce.
- Převod běží jednou na firmu, vychází z jejích aktuálních hodnot a automaticky
  doplňuje provozní cíle a mezikroky podle kategorie a zdrojového textu.
- Nové testy promíchají a hodnotí celou výrobní sekvenci. Nový snapshot obsahuje
  instrukce; staré snapshoty `ingredients`/`steps` se čtou bez přepočtu.
- Zdrojové znění zůstává interním migračním údajem a nezobrazuje se v UI.

## Consequences

- Detail, editor, test a nové výsledky sdílejí stejný význam pořadí.
- Automaticky odvozené pokyny může admin po převodu změnit.
- Dočasná duplicita legacy a kanonických tabulek je záměrná a umožňuje bezpečný
  rollout bez destruktivní migrace historického obsahu.
