# Posouzení odchylek docházky — trasovací matice

| ID  | Zdroj          | Požadavek                                         | Fáze | Stav    | Ověření                                     |
| --- | -------------- | ------------------------------------------------- | ---- | ------- | ------------------------------------------- |
| R1  | schválený plán | Odchylka hranice nad 15 minut                     | 1    | ověřeno | `AttendanceReportServiceTest`, `make check` |
| R2  | schválený plán | Jedno rozhodnutí pro více bloků směny             | 1    | ověřeno | `AttendanceDeviationReviewServiceTest`      |
| R3  | schválený plán | Auditované schválení nebo zamítnutí               | 1    | ověřeno | servisní a modelové testy                   |
| R4  | schválený plán | Synchronizace směny, snapshotu a otevřené výplaty | 1/3  | ověřeno | servisní a Playwright test                  |
| R5  | schválený plán | Blokace uzavřenou výplatou a potvrzení překryvu   | 2/3  | ověřeno | servisní/controller testy                   |
| R6  | schválený plán | Dialog, zaokrouhlení, štítky a revize             | 2    | ověřeno | type-check, build, Playwright               |
| R7  | schválený plán | Dokumentace architektonické výjimky               | 3    | ověřeno | ADR 0003, `make check`                      |
