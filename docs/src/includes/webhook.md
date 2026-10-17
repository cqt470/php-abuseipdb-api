# webhook.php

Usage documentation for [`src/includes/webhook.php`](../../../src/includes/webhook.php).

## Purpose

`Webhook` sends embed messages to a configured Discord webhook. It is used by the cache layer to notify about new cache entries and TTL evictions.

## Inclusion

```php
include_once __DIR__ . "/../../env.php";
```

In practice the file is loaded through the shared aggregator:

```php
include_once __DIR__ . "/../includes/index.php";
```

## Exposed class and public methods

- Class: `Zexa\Includes\Webhook`
- Public static methods:
  - `use_discord(array $embed_data = []): void`
- Private static methods:
  - `parse_color(int|string|null $color): int`

## `use_discord()`

```php
public static function use_discord(array $embed_data = []): void
```

Sends a single-embed message to the Discord webhook URL configured in `URL_DISCORD_WEBHOOK`.

Behavior:

- Returns immediately when `ENABLE_DISCORD_WEBHOOK` is `false`.
- The message username is `WEBHOOK_USERNAME`.
- Embed fields default to `[UNDEFINED]` for missing `title` and `description`, color `0` for a missing `color`, and the current server time (`date("c")`) for a missing `timestamp`.
- The request is a POST with JSON content type and a 10-second timeout, sent with `file_get_contents()` and `stream_context_create()` in line with the zero-dependency constraint.
- Throws `Exception("Didn't get a response from Discord")` when the transport fails, and `Exception("Discord rejected the webhook: ...")` when the response status is not `2xx`. Callers currently do not catch these exceptions, so a webhook failure surfaces as an uncaught error; this is a known limitation.

## `parse_color()`

Accepts either a 24-bit integer (used as-is) or a six-digit hexadecimal string with or without the leading `#` (converted with `hexdec()`). Any other value throws an exception describing the accepted formats.

## Embeds emitted by the codebase

| Title | Color | Trigger | Has `timestamp` |
| --- | --- | --- | --- |
| `New IP added to cache` | `#2ecc71` (green) | A new IP is stored in `cache/ips.json` | Yes |
| `TTL Reached` | `#444444` (gray) | An entry older than `CACHE_TTL` is evicted during a lookup | No |

## Minimal usage example

```php
Zexa\Includes\Webhook::use_discord([
    "title" => "New IP added to cache",
    "description" => "The ip \"8.8.8.8\" was added to the cache.",
    "color" => "#2ecc71",
    "timestamp" => date("c")
]);
```

## Internal dependencies

- [`example.env.php`](../../../example.env.php) (`ENABLE_DISCORD_WEBHOOK`, `URL_DISCORD_WEBHOOK`, `WEBHOOK_USERNAME`)

## Known limitations and TODOs

- No explicit TODO in this file.
- Thrown exceptions are not caught by current callers (cache eviction, cache insertion), so Discord outages surface as request failures.
- Requires valid `URL_DISCORD_WEBHOOK` and `ENABLE_DISCORD_WEBHOOK` configuration.
