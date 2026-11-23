# Discord webhook

[`src/includes/webhook.php`](../../../src/includes/webhook.php) sends Discord
embeds using the configured webhook URL.

```php
Webhook::use_discord(array $embed_data = [], discord_event $event = discord_event::generic, array $context = []): bool
Webhook::send_embeds(array $embeds): bool
```

The accepted embed keys are `title`, `description`, `color`, and `timestamp`.
`color` may be a 24-bit integer or a six-digit hexadecimal string with or
without `#`. Missing values default to `[UNDEFINED]`, `[UNDEFINED]`, `0`, and
the current ISO 8601 time respectively.

When `ENABLE_DISCORD_WEBHOOK` is false, the method returns `false` without
making a request. Otherwise it sends a JSON `POST` through
`file_get_contents()` with a 5-second timeout and returns `true` when Discord
answers with a 2xx status.

When `ENABLE_QUEUE` is on, `use_discord()` does not call Discord: it appends
`{event, context, embed}` to the `discord` queue and returns `true`. The cron
flush groups queued notifications by `event` (see [`Flusher`](./flusher.md)),
using `context.ip`. `send_embeds()` sends up to 10 embeds in one message right
away; the flush uses it.

Notifications are best-effort: a placeholder URL, transport failures, non-2xx
responses and invalid colors never interrupt the API request. They are written
to PHP's `error_log` and to `<LOGS_DIR>/webhook.log`, and the method returns
`false`.

The current callers emit:

| Title | Color | Event | Trigger |
| --- | --- | --- | --- |
| `New IP added to cache` | `#2ecc71` | `cache_added` | New cache entry |
| `TTL Reached` | `#444444` | `cache_expired` | Expired cache entry |
| `Failed authentication` | `#ff0000` | `failed_auth` | Rejected API token |
