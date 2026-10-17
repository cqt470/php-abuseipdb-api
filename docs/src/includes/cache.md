# cache.php

Usage documentation for [`src/includes/cache.php`](../../../src/includes/cache.php).

## Purpose

`Cache` manages the file-based JSON cache of IP reputation entries stored in `cache/ips.json`. It supports entry lookup, insertion, retrieval, deletion, and TTL-based eviction with Discord notifications.

## Inclusion

```php
include_once __DIR__ . "/index.php";
```

In practice the file is loaded through the shared aggregator:

```php
include_once __DIR__ . "/../includes/index.php";
```

## Exposed class and public methods

- Class: `Zexa\Includes\Cache`
- Public methods:
  - `__construct(string $filepath)`
  - `check_for_entry(string $key, string $value): bool`
  - `add_entry(array $ip): void`
  - `get_entry(string $key, string $value): ?array`
  - `delete_entry(string $key, string $value): void`

## `__construct()`

Requires the cache file to exist, otherwise throws `Exception("File doesn't exist.")`. In a fresh clone the file is not tracked by git, so it must be created before first use (for example with `echo "[]" > cache/ips.json`).

## `check_for_entry()`

```php
public function check_for_entry(string $key, string $value): bool
```

Reads the cache file and iterates the stored entries. Two checks run for each entry, in order:

1. **TTL enforcement**: if the entry's `added_at` timestamp is older than `time() - CACHE_TTL`, the method calls `delete_entry()` with the queried key and value, sends a Discord webhook (title `TTL Reached`, description naming the queried address, color `#444444`), and returns `false`.
2. **Match**: if `$ip[$key] == $value`, the method returns `true`.

If the loop completes without a match, the method returns `false`.

Known caveats, reproduced faithfully from the current implementation:

- Eviction inside the loop targets the **queried** entry rather than the expired one that triggered the eviction, and each eviction re-reads and rewrites the whole JSON file. Multiple file reads per request are possible. A rework of this logic is planned; do not rely on the current eviction semantics.
- Entries carry `added_at` alongside the upstream `data` payload; `get_entry()` returns it as part of the entry.

## `add_entry()`

```php
public function add_entry(array $ip): void
```

Expects the decoded AbuseIPDB response structure with `$ip["data"]["ipAddress"]` set. Behavior:

1. Re-reads the cache and returns early when `check_for_entry("ipAddress", ...)` reports the address is already cached.
2. Sends a Discord webhook (title `New IP added to cache`, color `#2ecc71`, with an ISO 8601 `timestamp`).
3. Adds `added_at = time()` to the `data` payload, appends the entry, and rewrites the file with `JSON_UNESCAPED_SLASHES`.

Known caveat: the caller in `Security::get_ip_info()` invokes `add_entry()` unconditionally after an upstream call, so upstream error payloads may be cached as well.

## `get_entry()`

```php
public function get_entry(string $key, string $value): ?array
```

Returns the first entry whose `$key` equals `$value`, or `null` when there is no match. The returned array includes `added_at`.

## `delete_entry()`

```php
public function delete_entry(string $key, string $value): void
```

Rewrites `cache/ips.json` without the entries whose `$key` equals `$value`. This method is part of the public API of `Cache` and is expected to be reused by future GDPR deletion endpoints.

## Minimal usage example

```php
$cache = new Zexa\Includes\Cache(__DIR__ . "/../../cache/ips.json");

if(!$cache->check_for_entry("ipAddress", "8.8.8.8")){
    // not cached: fetch from upstream and store
}
```

## Internal dependencies

- [`src/includes/index.php`](./index.md)
- [`src/includes/webhook.php`](./webhook.md)
- [`example.env.php`](../../../example.env.php) (`CACHE_TTL`)

## Known limitations and TODOs

- `check_for_entry` currently expects string values (`@todo add more $value datatypes` in source).
- Duplicate detection in `add_entry` is based on `data.ipAddress`.
- Eviction semantics and per-eviction file rewrites are known inefficiencies scheduled for rework; see the caveats above.
