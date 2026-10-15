# webhook.php

Usage documentation for [`src/includes/webhook.php`](../../../src/includes/webhook.php).

## Purpose

`Webhook` sends Discord webhook embeds when enabled.

## Inclusion

```php
include_once __DIR__ . "/../../../src/includes/webhook.php";
```

## Exposed class and public methods

- Class: `Zexa\Includes\Webhook`
- Public static methods:
  - `use_discord(array $embed_data = []): void`

## Minimal usage example

```php
Zexa\Includes\Webhook::use_discord([
    "title" => "New IP added to cache",
    "description" => "IP 8.8.8.8 stored",
    "color" => "#2ecc71",
    "timestamp" => date("c")
]);
```

## Internal dependencies

- [`env.php`](../../../env.php)

## Known limitations and TODOs

- No explicit TODO in this file.
- Throws exceptions on transport failure and non-2xx responses.
- Requires valid `URL_DISCORD_WEBHOOK` and `ENABLE_DISCORD_WEBHOOK` configuration.
