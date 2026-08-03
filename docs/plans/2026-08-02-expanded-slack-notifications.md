# Plán rozšířených Slack notifikací

## Fáze 1: Firemní kanál a infrastruktura

- Přidat persistence, getter, validaci, admin endpoint a kartu Nastavení.
- Zobecnit Slack destination na volitelný store kontext.
- Přidat firemní dispatch a nové typy aktivit.

## Fáze 2: Store provozní milníky

- Posouzení odchylky docházky.
- Agregované checklistové přechody.
- Uzavření a reopen mzdového a finančního reportu.

## Fáze 3: Firemní milníky

- Výsledky tříreceptových a legacy testů bez duplicit.
- Vydání a void voucheru do firemního kanálu.
- Redemption a reversal voucheru do store kanálu.

## Fáze 4: Dokončení a verifikace

- Doplnit CS/SK/EN UI a Slack překlady.
- Spustit cílené testy, type-check, build a `make check`.
- Zapsat čerstvé důkazy do verification dokumentu.
