# Rating docházky ve směnách – průběh

| Požadavek                      | Stav    | Důkaz                                     |
| ------------------------------ | ------- | ----------------------------------------- |
| Doménový výpočet a pásma       | Ověřeno | `AttendanceRatingServiceTest`             |
| Absence, čekající a více bloků | Ověřeno | Servisní hraniční scénáře                 |
| Inertia kontrakt a role        | Ověřeno | `ShiftIndexControllerTest`                |
| Kalendář, detail a souhrn      | Ověřeno | Type-check, build a browser smoke         |
| CS/EN/SK a přístupnost         | Ověřeno | I18n parity a přístupné popisky           |
| Cílené a plné ověření          | Ověřeno | `make check`: 580 PHP + 19 frontend testů |

Blokátory: žádné.
