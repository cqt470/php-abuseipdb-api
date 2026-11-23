# Discord webhook

[`src/includes/webhook.php`](../../../src/includes/webhook.php) sends one Discord
embed using the configured webhook URL.

```php
Webhook::use_discord(array $embed_data = []): bool
```

The accepted embed keys are `title`, `description`, `color`, and `timestamp`.
`color` may be a 24-bit integer or a six-digit hexadecimal string with or
without `#`. Missing values default to `[UNDEFINED]`, `[UNDEFINED]`, `0`, and
the current ISO 8601 time respectively.

When `ENABLE_DISCORD_WEBHOOK` is false, the method returns `false` without
making a request. Otherwise it sends a JSON `POST` through
`file_get_contents()` with a 5-second timeout and returns `true` when Discord
answers with a 2xx status.

Notifications are best-effort: a placeholder URL, transport failures, non-2xx
responses and invalid colors never interrupt the API request. They are written
to PHP's `error_log` and to `<LOGS_DIR>/webhook.log`, and the method returns
`false`.

The current callers emit:

| Title | Color | Trigger |
| --- | --- | --- |
| `New IP added to cache` | `#2ecc71` | New cache entry |
| `TTL Reached` | `#444444` | Expired cache entry |
| `Failed authentication` | `#ff0000` | Rejected API token |
