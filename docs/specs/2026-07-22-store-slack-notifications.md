# Store-Specific Slack Notifications

## Source

Approved user plan from 2026-07-22.

## Requirements

- Install `laravel/slack-notification-channel:^3.8` and use one deployment-wide Slack bot token.
- Add an optional Slack channel to every store and expose it in the admin create, edit, and detail surfaces.
- Send Czech, queued, post-commit operational notifications to each affected configured store channel.
- Cover attendance transitions/corrections, finalized inventory, statement save/clear/restore, and manual stock movement create/reverse.
- Send transfers and transfer reversals to source and destination channels, using direction-specific wording and deduplicating shared channels.
- Exclude drafts, autosaves, cancellations, internal snapshots, inventory reconciliations, failed requests, and rolled-back writes.
- Slack configuration and delivery failures must never invalidate a successful business operation.

## Decisions

- `stores.slack_channel` is nullable, trimmed, and not unique.
- Stores without a channel and deployments without a bot token remain silent.
- Slack messages use fixed Czech copy and `Europe/Prague` timestamps.
- Messages contain scalar snapshots only: event, actor email, store, relevant identifiers/aggregates, timestamp, and a stable authenticated link.
- Correction reasons, audit snapshots, and item-level payloads are excluded.

## External Handoff

The operator must create the Slack App, grant the required chat scopes, configure `SLACK_BOT_USER_OAUTH_TOKEN`, and invite the bot to private store channels. Real Slack delivery cannot be completed without those credentials.
