# Discord webhook

[`src/includes/webhook.php`](../../../src/includes/webhook.php) sends one Discord
embed using the configured webhook URL.

```php
Webhook::use_discord(array $embed_data = []): void
```

The accepted embed keys are `title`, `description`, `color`, and `timestamp`.
`color` may be a 24-bit integer or a six-digit hexadecimal string with or
without `#`. Missing values default to `[UNDEFINED]`, `[UNDEFINED]`, `0`, and
the current ISO 8601 time respectively.

When `ENABLE_DISCORD_WEBHOOK` is false, the method returns without making a
request. Otherwise it sends a JSON `POST` through
`file_get_contents()` with a 10-second timeout. Transport failures and
non-2xx responses throw `Exception`.

The current callers emit:

| Title | Color | Trigger |
| --- | --- | --- |
| `New IP added to cache` | `#2ecc71` | New cache entry |
| `TTL Reached` | `#444444` | Expired cache entry |
