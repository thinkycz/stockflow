# Výplatní pásky — traceability matrix

| Req ID | Source       | Requirement / Design Input                       | Phase | Tasks                   | Status   | Verification        | Notes                                |
| ------ | ------------ | ------------------------------------------------ | ----- | ----------------------- | -------- | ------------------- | ------------------------------------ |
| R1     | written spec | Admin-only měsíční report aktivní prodejny       | 2     | routy, stránka, sidebar | verified | verification record | omezené účty bez navigace i přístupu |
| R2     | written spec | Základ z plánovaných směn a kontrolní docházka   | 1     | výplatní servis         | verified | verification record | neúplná docházka pouze varuje        |
| R3     | written spec | Položkové spropitné/srážky bez záporné výplaty   | 1–2   | servis, CRUD            | verified | verification record | důvod povinný                        |
| R4     | written spec | Uzavření, snapshot a znovuotevření               | 1–2   | lifecycle               | verified | verification record | úpravy se zachovají                  |
| R5     | written spec | Hromadný i jednotlivý tisk                       | 2     | print route/page        | verified | verification record | page break na brigádníka             |
| R6     | written spec | Finance čerpají pásky a dodržují pořadí uzavření | 3     | FinancialReportService  | verified | verification record | finanční override zůstává            |
| R7     | written spec | CZ/EN/SK UI a plná verifikace                    | 2–4   | i18n, checks, E2E       | verified | verification record | žádný designový zdroj                |
| R8     | follow-up    | Ruční měsíční hodiny a sazba s resetem           | ext.  | servis, CRUD, UI        | verified | verification record | přepisuje automatický základ         |
| R9     | follow-up    | Zjednodušený tisk čtyř výsledných hodnot         | ext.  | print route/page        | verified | verification record | bez tabulky směn a docházky          |

Verifikační záznam: `docs/verification/2026-08-01-payroll.md`.
