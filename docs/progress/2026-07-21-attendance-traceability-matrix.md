# Docházka brigádníků – traceability matrix

| Req ID | Source       | Requirement                             | Phase | Tasks                     | Status   | Verification                          | Notes                           |
| ------ | ------------ | --------------------------------------- | ----- | ------------------------- | -------- | ------------------------------------- | ------------------------------- |
| R1     | written spec | Aktivní maloobchodní prodejna a role    | 2-3   | routes, controllers, UI   | verified | controller/service tenancy tests      | sklad bez ovládání              |
| R2     | written spec | Stavový automat a databázové invarianty | 1     | service, schema           | verified | AttendanceServiceTest                 | jeden blok a pauza              |
| R3     | written spec | Párování směny ±60 minut a potvrzení    | 1-3   | service, modal            | verified | service + type-check                  | snapshot plánu a sazby          |
| R4     | written spec | Agregovaná obsazenost a stale stav      | 1-3   | service, UI               | verified | AttendanceReportServiceTest           | tři stavy                       |
| R5     | written spec | Adminské auditované opravy              | 1-3   | audit, controllers, forms | verified | correction service/controller tests   | create/update/void              |
| R6     | written spec | Měsíční čas, odchylka a odměna          | 2-3   | report service, UI        | verified | report tests                          | přesné sekundy a hranice měsíce |
| R7     | written spec | Tisková HTML stránka                    | 2-3   | print controller/page     | verified | controller test + production build    | bez PDF/CSV                     |
| R8     | written spec | UTC persistence a Europe/Prague UX      | 1-2   | service, formatting       | verified | service/report tests                  | Carbon instant calculations     |
| R9     | written spec | CS/EN/SK a dokumentace                  | 3-4   | i18n, docs                | verified | parity tests, type-check, docs review | parity required                 |
