# Checklisty provozovny

## Zdroj pravdy

- Schválený plán z 1. 8. 2026 po `grill-with-docs` sezení.
- `denni_smeny_teacha.pdf`: každodenní ranní a odpolední úkoly.
- `tydenni_uklid_teacha_cz.pdf`: doplňkové úklidové úkoly Po–Ne pro obě směny.

## Funkční požadavky

- Admin spravuje store-scoped šablony na `/checklists`; omezený účet správu ani historii nevidí.
- Šablona kombinuje každodenní a weekday úkoly pro ranní a odpolední směnu.
- Aktivní retail provozovna dostane každý pražský den neměnný snapshot; změny šablony platí od dalšího dne.
- Dashboard zobrazuje obě směny adminovi i omezenému účtu. Každé splnění vyžaduje výběr brigádníka a ukládá brigádníka, auditního uživatele, čas a událost.
- Dnešní položky lze splnit i odškrtnout; historické položky jsou neměnné.
- Admin může den auditovaně omluvit nebo omluvení zrušit s důvodem.
- Historie uchovává všechny dny a nabízí filtry provozovny, data, stavu a brigádníka.
- Checklisty neblokují docházku, výkazy ani uzávěrky.

## Stavové a bezpečnostní invarianty

- Směna má stav `not_configured`, `in_progress`, `completed`, `incomplete` nebo `excused`.
- Snapshot je unikátní pro provozovnu a datum; generování je idempotentní.
- Zápis položky kontroluje firmu, provozovnu, retail typ, dnešní pražské datum, brigádníka a verzi.
- Sklady nedostávají šablony ani snapshoty; neaktivní retail provozovny nedostávají denní snapshoty.
- Všechny texty jsou dostupné v češtině, slovenštině a angličtině.
