# Rozšířené Slack notifikace – traceability matrix

| Req ID | Source             | Requirement                                         | Phase | Tasks | Status   | Verification                                 | Notes                          |
| ------ | ------------------ | --------------------------------------------------- | ----- | ----- | -------- | -------------------------------------------- | ------------------------------ |
| R1     | schválený plán     | Admin spravuje nullable firemní Slack kanál         | 1     | T1.1  | verified | SettingsControllerTest, UserTest, type-check | Admin-only UI hotovo           |
| R2     | schválený plán     | Firemní dispatch bez falešného store kontextu       | 1     | T1.2  | verified | listener a notification testy                | Store API zůstalo kompatibilní |
| R3     | schválený plán     | Posouzení odchylky docházky                         | 2     | T2.1  | verified | AttendanceDeviationReviewServiceTest         | Bez důvodu rozhodnutí          |
| R4     | schválený plán     | Agregované checklistové milníky                     | 2     | T2.2  | verified | ChecklistServiceTest                         | Ne item-level zprávy           |
| R5     | schválený plán     | Payroll close a reopen                              | 2     | T2.3  | verified | PayrollReportServiceTest                     | Se souhrnnými částkami         |
| R6     | schválený plán     | Financial close a reopen                            | 2     | T2.4  | verified | FinancialReportServiceTest                   | Příjmy, výdaje, zisk           |
| R7     | schválený plán     | Receptové výsledky bez duplicit                     | 3     | T3.1  | verified | RecipeTestSessionServiceTest                 | Success i failure              |
| R8     | schválený plán     | Voucher lifecycle se správným routingem             | 3     | T3.2  | verified | GiftVoucherServiceTest                       | Bez kódu a důvodu              |
| R9     | původní Slack spec | Post-commit, queued a failure-isolated doručení     | 1–3   | T1.3  | verified | listener testy                               | Chybějící konfigurace je no-op |
| R10    | schválený plán     | CS/SK/EN UI a české Slack texty                     | 4     | T4.1  | verified | I18nParityTest, type-check                   | Překladové soubory synchronní  |
| R11    | schválený plán     | Vyloučené CRUD/draft/read-only toky zůstávají tiché | 4     | T4.2  | verified | cílené controller/service regresní testy     | Bez dodatečných zpráv          |

Kompletní výsledky jsou v [verification záznamu](../verification/2026-08-02-expanded-slack-notifications.md).
