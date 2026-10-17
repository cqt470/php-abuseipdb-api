# cache.php

Reference for [`src/includes/cache.php`](../../../src/includes/cache.php).

## Purpose

`Cache` manages JSON file cache operations for AbuseIPDB responses.

## Public API

- `__construct(string $filepath)`
- `check_for_entry(string $key, string $value): bool`
- `add_entry(array $ip): void`
- `get_entry(string $key, string $value): ?array`
- `delete_entry(string $key, string $value): void`

## Behavior notes

`check_for_entry()` validates TTL using `CACHE_TTL` and removes expired entries through `delete_entry()`.

When an entry expires, it sends a Discord webhook with:

- title: `TTL Reached`
- color: `#444444`

`add_entry()` sends another webhook when adding a new IP:

- title: `New IP added to cache`
- color: `#2ecc71`
- timestamp: current ISO 8601 time

## Example

```php
$cache = new Zexa\Includes\Cache(__DIR__ . "/../../../cache/ips.json");
$exists = $cache->check_for_entry("ipAddress", "8.8.8.8");
```

## Dependencies

- [`src/includes/index.php`](../../../src/includes/index.php)
- [`src/includes/webhook.php`](../../../src/includes/webhook.php)
- `env.php` (runtime local file created from `example.env.php`)

## Known caveats

- `check_for_entry()` calls `delete_entry()` inside its loop and rewrites cache data during lookup.
- Value comparison currently assumes string values.
