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

## Original Slack failure

After failed-job persistence was corrected, the provider exception was recorded as `Slack API call failed with error [missing_scope]` at the package's `chat.postMessage` request.

The configured bot token has not been granted Slack's required `chat:write` bot scope. Adding the scope requires reinstalling the Slack App to the workspace so the installation and token receive the new permission.

For channels the bot joins explicitly, `chat:write` is sufficient. Posting to public channels without inviting the bot additionally requires `chat:write.public`; private channels always require inviting the bot.
