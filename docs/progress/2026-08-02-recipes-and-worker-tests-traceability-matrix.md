# Recepty a testování brigádníků – traceability matrix

| Req ID | Source         | Požadavek                                            | Fáze | Stav     | Ověření                                          |
| ------ | -------------- | ---------------------------------------------------- | ---- | -------- | ------------------------------------------------ |
| R1     | schválený plán | Firemní katalog a přesný jednorázový import PDF      | 1    | verified | `RecipeServiceTest`                              |
| R2     | schválený plán | Admin CRUD, řazení a archivace                       | 2–3  | verified | controller testy, admin E2E                      |
| R3     | schválený plán | Omezený read-only katalog                            | 2–3  | verified | controller testy, limited E2E a runtime snapshot |
| R4     | schválený plán | Náhodná varianta a řazení kroků                      | 1–3  | verified | service/controller testy, limited E2E            |
| R5     | schválený plán | Přesné serverové vyhodnocení a snapshot historie     | 1–2  | verified | `RecipeServiceTest`, `RecipeTestControllerTest`  |
| R6     | schválený plán | Adminský detail brigádníka a historie                | 2–3  | verified | result controller testy, admin E2E               |
| R7     | schválený plán | Role, tenancy a ochrana pokusů                       | 2    | verified | recipe feature testy                             |
| R8     | schválený plán | Navigace a CS/SK/EN UI                               | 3    | verified | sidebar kontrakt a i18n parity                   |
| R9     | schválený plán | Mobilní a přístupné řazení                           | 3–4  | verified | mobilní E2E, tlačítka nahoru/dolů                |
| R10    | schválený plán | Testy, build, plná kontrola a dokumentace            | 4    | verified | `make check`, cílené E2E, verification report    |
| R11    | follow-up plán | Inline katalog podle kategorií bez rozkliknutí       | 5    | verified | `RecipeIndexControllerTest`, limited E2E         |
| R12    | follow-up plán | Suroviny jako řádky s množstvím, fallbackem a ikonou | 5    | verified | parser/service/CRUD testy, runtime katalog       |
| R13    | follow-up plán | Postupové kroky s akční ikonou a pořadím             | 5    | verified | parser, snapshot a mobile E2E                    |
| R14    | follow-up plán | Test řadí pouze postup a snapshot je celý            | 5    | verified | `RecipeServiceTest`, `RecipeTestControllerTest`  |
| R15    | follow-up plán | Historické pokusy zůstávají kompatibilní             | 5    | verified | legacy fallback payload + service test           |
