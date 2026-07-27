# MySQL user role CHECK migration

## Symptom

MySQL rejected `users_role_shape_check` with error 3823 because
`assigned_store_id` was also used by a foreign key with `ON DELETE SET NULL`.
The same conflict applied to `parent_user_id`.

## Root cause

The role invariant requires both foreign keys to be present for every limited
account. The legacy referential actions could null either key when its parent
row was deleted. MySQL correctly refuses a CHECK constraint that conflicts
with such a referential action.

## Fix

Migration `2026_07_19_000007` replaces both role foreign keys with `ON DELETE
RESTRICT` before adding the CHECK. Its development rollback restores the
legacy actions after dropping the CHECK. Store deletion now reports the
existing validation error when a limited account is assigned to the store.

The originally pending migration was subsequently applied successfully to the
local MySQL database. Both foreign keys report `ON DELETE RESTRICT`, and the
role-shape CHECK is present in `information_schema`.

## Bootstrap follow-up

After the CHECK became active, `UserSeeder` exposed a second invalid transient
state: it inserted `test@test.com` with the factory's legacy limited-user
defaults and promoted it to admin in a later UPDATE. The CHECK correctly
rejected the INSERT. The seeder now applies the factory's `admin()` state
before creation, so the bootstrap account is valid in its first database row.

## Recurrence prevention

Before adding a CHECK over foreign-key columns, verify that every referential
action can produce only states accepted by that CHECK.
