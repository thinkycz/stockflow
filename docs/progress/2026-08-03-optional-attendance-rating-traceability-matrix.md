# Volitelné hodnocení docházky – traceability matrix

| Req ID | Zdroj          | Požadavek                                      | Fáze | Úkoly      | Stav     | Ověření                                                              | Poznámky                         |
| ------ | -------------- | ---------------------------------------------- | ---- | ---------- | -------- | -------------------------------------------------------------------- | -------------------------------- |
| R1     | schválený plán | Defaultně zapnuté persistované nastavení       | 1    | T1.1, T1.2 | verified | [evidence](../verification/2026-08-03-optional-attendance-rating.md) | Pro nové i existující brigádníky |
| R2     | schválený plán | Checkbox ve formulářích a stav v seznamu       | 1, 3 | T1.2, T3.1 | verified | [evidence](../verification/2026-08-03-optional-attendance-rating.md) | Admin-only sekce                 |
| R3     | schválený plán | Vypnutý rating se nepočítá ani neposkytuje     | 2    | T2.1       | verified | [evidence](../verification/2026-08-03-optional-attendance-rating.md) | Hard enforcement ve službě       |
| R4     | schválený plán | Docházka, hodiny a mzda zůstávají              | 2    | T2.2       | verified | [evidence](../verification/2026-08-03-optional-attendance-rating.md) | Ratingové metriky jsou nullable  |
| R5     | schválený plán | Interní i veřejné obrazovky respektují opt-out | 2, 3 | T2.2, T3.2 | verified | [evidence](../verification/2026-08-03-optional-attendance-rating.md) | Bez úniku ratingových dat        |
| R6     | schválený plán | `CircleOff`, tooltip a přístupný text          | 3    | T3.2       | verified | [evidence](../verification/2026-08-03-optional-attendance-rating.md) | CS/EN/SK                         |
| R7     | schválený plán | Opětovné zapnutí obnoví historický rating      | 2, 4 | T2.1, T4.1 | verified | [evidence](../verification/2026-08-03-optional-attendance-rating.md) | Bez změny docházkových dat       |
