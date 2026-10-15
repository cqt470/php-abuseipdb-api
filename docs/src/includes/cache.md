# cache.php

Usage documentation for [`src/includes/cache.php`](../../../src/includes/cache.php).

## Purpose

`Cache` manages file-based JSON cache operations for IP entries.

## Inclusion

```php
include_once __DIR__ . "/../../../src/includes/cache.php";
```

## Exposed class and public methods

- Class: `Zexa\Includes\Cache`
- Public methods:
  - `__construct(string $filepath)`
  - `check_for_entry(string $key, string $value): bool`
  - `add_entry(array $ip): void`
  - `get_entry(string $key, string $value): ?array`

## Minimal usage example

```php
$cache = new Zexa\Includes\Cache(__DIR__ . "/../../../cache/ips.json");
$exists = $cache->check_for_entry("ipAddress", "8.8.8.8");
```

## Internal dependencies

- [`src/includes/index.php`](../../../src/includes/index.php)
- [`src/includes/webhook.php`](../../../src/includes/webhook.php)

## Known limitations and TODOs

- Cache file has no TTL mechanism.
- `check_for_entry` currently expects string values (`@todo add more $value datatypes` in source).
- Duplicate detection in `add_entry` is based on `data.ipAddress`.
