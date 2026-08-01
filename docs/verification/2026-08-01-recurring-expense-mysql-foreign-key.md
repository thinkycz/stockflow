# Recurring Expense MySQL Foreign Key Verification

## Claim

The recurring-expense migration no longer creates an identifier above MySQL's
64-character limit and can recover from the partially applied production state.

## Evidence

- The new architecture test failed before the fix with a generated length of 75
  and passes after the explicit constraint name was introduced.
- `FinancialRecurringExpenseMigrationTest` removes the relationship constraint,
  reruns the migration with both tables already present, and verifies that the
  missing foreign key is restored.
- `php artisan migrate:fresh --env=testing --force` applies all migrations,
  including `2026_08_01_000004`, successfully.

## Result

Verified locally. The corrected revision is ready for a Forge deployment retry.
