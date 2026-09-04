# ADR 0009: Domain and feature boundaries

Status: accepted, September 4 remediation plan.

Business services and shared commands belong to `App\Domain\<Module>`. Modules
are Identity, Stores, Catalog, Inventory, Statements, Workforce, Payroll,
Finance, BankStatements, Recipes, GiftVouchers, Checklists, Noticeboard and
OperationalActivity. Models, HTTP controllers, jobs and notifications retain
conventional locations and stable names. Assistant executors adapt arguments,
approvals and results into the same mutations as controllers.

Allowed cross-domain dependencies:

| Consumer              | Dependencies                                     |
| --------------------- | ------------------------------------------------ |
| Stores                | Inventory read projections, Checklists lifecycle |
| Statements            | Workforce attendance lifecycle                   |
| Payroll               | Workforce attendance reports                     |
| Finance               | Payroll reports, Workforce attendance            |
| OperationalActivity   | Finance reports                                  |
| All remaining domains | None                                             |

`DomainArchitectureTest` rejects unlisted edges, cycles, controller/assistant
imports and obsolete flat implementations. Shared Eloquent models, enums,
validity rules and framework abstractions are outside this module graph.
`App\Support\OperationalActivityService` is the shared transactional journal
primitive; digest assembly stays in OperationalActivity. This separation avoids
making every writing domain depend on the digest/report dependency tree.
Money and decimal commission constants also remain shared primitives. Their
consumers retain workflow-specific rounding points and historical snapshots.

Inventory lifecycle is separate from history/forecast projections; financial
and payroll mutations are separate from report assembly. Administration is
split by owned resource. The inventory save contract uses immutable `InventoryDraftRowInput` across
HTTP and assistant adapters. Substantial new cross-layer contracts use typed
objects; small mutations retain simple arguments. Existing serialized payloads
must remain compatible unless explicitly migrated.

Frontend page entrypoints keep their paths and routing/props composition.
`features/<feature>` owns workflow state, components, calculations and types.
Shared UI, layouts, localization, dialogs and error handling remain centralized.
No forwarding modules are retained at old component/service paths.

Consequences: application/assets and restarted workers deploy together; this
is not a rolling compatibility deployment. The core package stays independent
of StockFlow domains. Its generic scaffolds retain framework entrypoints;
app-specific domain workflows are composed explicitly after scaffolding and
checked by the architecture suite.

## Module ownership

| Module              | Owned business behavior                                                    |
| ------------------- | -------------------------------------------------------------------------- |
| Identity            | Account/profile lifecycle, users and password recovery                     |
| Stores              | Store administration, switching and store-detail projection                |
| Catalog             | Item administration                                                        |
| Inventory           | Stock movements, inventory lifecycle, consumption/history/forecast reports |
| Statements          | Daily/monthly statement snapshots and mutations                            |
| Workforce           | Workers, attendance, shifts, requests, presets and public sharing          |
| Payroll             | Payslip assembly, exact wages and payroll mutations                        |
| Finance             | Financial report assembly, recurring expenses and report lifecycle         |
| BankStatements      | Imports, generation recovery, review and reconciliation                    |
| Recipes             | Catalog instructions/adjustments and atomic test sessions                  |
| GiftVouchers        | Issuance, redemption/reversal, voiding and branding                        |
| Checklists          | Templates, daily snapshots, completion and excuses                         |
| Noticeboard         | Sanitized cards, private images and recycle lifecycle                      |
| OperationalActivity | Retention, digests and company notification configuration                  |
