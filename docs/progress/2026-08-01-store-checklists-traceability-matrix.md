# Checklisty provozovny – Traceability Matrix

| Req ID | Source        | Requirement                                          | Phase | Status   | Verification                                |
| ------ | ------------- | ---------------------------------------------------- | ----- | -------- | ------------------------------------------- |
| R1     | approved plan | Admin-only `/checklists` pod Docházkou               | 2–3   | verified | controller, sidebar unit + Chromium E2E     |
| R2     | daily PDF     | Přesné každodenní úkoly pro obě směny                | 1     | verified | ChecklistServiceTest                        |
| R3     | weekly PDF    | Přesné Po–Ne úkoly pro obě směny                     | 1     | verified | ChecklistServiceTest                        |
| R4     | approved plan | Retail výchozí šablony a idempotentní denní snapshot | 1–2   | verified | service, command and store controller tests |
| R5     | approved plan | Dvě dashboardové kartičky s výběrem brigádníka       | 2–3   | verified | dashboard feature + Chromium E2E            |
| R6     | approved plan | Verze, časová hranice a store/company autorizace     | 1–2   | verified | checklist item edge tests                   |
| R7     | approved plan | Plná historie, filtry, detail a auditované omluvení  | 2–3   | verified | history pagination + excuse tests           |
| R8     | approved plan | Synchronized CS/SK/EN UI                             | 3     | verified | i18n parity, type-check and build           |
