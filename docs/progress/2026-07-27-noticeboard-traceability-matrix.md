# Virtuální nástěnka – traceability matrix

| Req ID | Source        | Requirement                                                  | Phase | Tasks                           | Status   | Verification                           | Notes                     |
| ------ | ------------- | ------------------------------------------------------------ | ----- | ------------------------------- | -------- | -------------------------------------- | ------------------------- |
| R1     | approved plan | Nástěnka aktivní prodejny nad současným dashboardem          | 2–3   | dashboard contract, UI          | verified | dashboard + E2E pass                   | current dashboard remains |
| R2     | approved plan | Admin i omezený uživatel mohou vytvářet a sdíleně upravovat  | 1–2   | CRUD, authorization             | verified | role/store feature tests + E2E pass    | server derives store      |
| R3     | approved plan | Rich text s omezeným bezpečným formátováním                  | 1–3   | sanitizer, Tiptap               | verified | sanitizer tests, type-check, build     | safe subset only          |
| R4     | approved plan | Jeden privátní validovaný obrázek                            | 1–2   | storage service, image endpoint | verified | upload/replace/remove/scope tests pass | auth + store scope        |
| R5     | approved plan | Aktivní, expirované, hledání, štítek, 24/page                | 2–3   | query contract, filters         | verified | filter and pagination tests pass       | newest first              |
| R6     | approved plan | Sticky-note mřížka, detail a modal formuláře                 | 3     | Vue components                  | verified | type-check, build, browser flow pass   | responsive                |
| R7     | approved plan | Optimistický zámek zabrání stale overwrite                   | 1–2   | lock version                    | verified | stale update feature test passes       | atomic transaction lock   |
| R8     | approved plan | Soft-delete, admin koš, obnova a 30denní úklid               | 2–4   | trash endpoints, command        | verified | lifecycle and prune tests pass         | daily scheduler           |
| R9     | approved plan | Zachovat současné role a dashboard workflow                  | 2–3   | additive integration            | verified | full make check + dashboard E2E pass   | existing tests retained   |
| R10    | UX follow-up  | Jeden nadpis, Nástěnka první v Prodejně, globální store pill | 3     | navigation, layout, route scope | verified | unit, type-check, dashboard E2E        | shared route classifier   |
