# Slack Queue Failure Investigation

## Symptom

A queued `OperationalActivitySlackNotification` failed. While Laravel attempted to persist that failure, it raised:

`Database file at path [stockflow] does not exist (Connection: sqlite)`

## Evidence

- The failed payload identifies the Redis `default` queue and the Slack notification job.
- The effective failing connection was SQLite and its database value was `stockflow`.
- `stockflow` is the configured MySQL database name, not an absolute SQLite file path.
- `config/database.php` falls back to `mysql` when `DB_CONNECTION` is absent.
- Before the fix, `config/queue.php` separately fell back to `sqlite` for batches and failed jobs when `DB_CONNECTION` was absent.
- The local environment explicitly sets `DB_CONNECTION=mysql`, so the mismatch only appears in an environment or stale worker configuration where that variable is absent.

## Root cause

The queue failure repository used a database fallback inconsistent with the application's default database connection. This secondary exception masked the original Slack delivery exception.

## Fix

Use the same MySQL fallback for queue batch and failed-job storage as the main database configuration, and add a regression test for an absent `DB_CONNECTION`.

## Remaining evidence needed

After deployment and worker restart, inspect the recorded failed job or worker log for the original Slack exception. Likely operational causes include an invalid token, an unknown channel, or a bot that has not been invited, but none is proven by the supplied trace.
