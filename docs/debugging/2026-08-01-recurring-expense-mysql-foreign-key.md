# Recurring Expense MySQL Foreign Key Deployment Failure

## Symptom

Forge deployment release `74570063` failed while running
`2026_08_01_000004_create_financial_recurring_expenses.php`. MySQL rejected the
automatically generated foreign-key identifier with error 1059.

## Evidence and root cause

- Laravel generated
  `financial_recurring_expense_versions_financial_recurring_expense_id_foreign`.
- The generated identifier is 75 characters; MySQL identifiers are limited to
  64 characters.
- A repository-wide scan found no other automatic migration foreign-key name
  above the MySQL limit.
- MySQL DDL is not rolled back with the failed migration, so both newly created
  tables may remain even though the migration was not recorded as complete.

## Fix

- Use the explicit short constraint name `fin_rec_exp_versions_expense_fk`.
- Make this migration recover the observed partial state by skipping tables that
  already exist and adding the missing foreign key independently.
- Add an architecture guard for automatic foreign-key names and a migration
  regression test for repair of a partially applied constraint.

## Deployment follow-up

Deploy the corrected revision and retry the failed deployment. The migration now
handles the tables left by release `74570063`; no manual table deletion is
required.
