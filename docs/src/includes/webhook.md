# webhook.php

Reference for [`src/includes/webhook.php`](../../../src/includes/webhook.php).

## Purpose

`Webhook` sends Discord embeds when webhook support is enabled.

## Public API

- `use_discord(array $embed_data = []): void`

## Expected payload fields

`$embed_data` supports:

- `title` (string|null)
- `description` (string|null)
- `color` (int|string|null)
- `timestamp` (string|null)

If `timestamp` is omitted, the method uses current ISO 8601 time.

## Example

```php
Zexa\Includes\Webhook::use_discord([
    "title" => "New IP added to cache",
    "description" => "IP 8.8.8.8 stored",
    "color" => "#2ecc71",
]);
```

## Dependencies

- `env.php` (runtime local file created from `example.env.php`)

## Error handling

- Returns early when `ENABLE_DISCORD_WEBHOOK` is `false`.
- Throws exceptions when the request fails or Discord responds with a non-2xx status.
