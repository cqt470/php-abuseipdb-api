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

Checks whether a key/value pair exists in the cache and enforces TTL.

- Signature: `check_for_entry(string $key, string $value): bool`
- Scans the cache file for entries.
- **TTL enforcement**: If an entry is older than `time() - CACHE_TTL`, it calls `delete_entry()` using the **expired entry's own key and value**. It sends a Discord webhook (title `TTL Reached`) and then **continues** scanning the rest of the cache.
- If it finds the requested `$value`, it returns `true`.
- Returns `false` if the value is not found.

> Note: The loop continues after an eviction, ensuring that valid entries located after a stale entry are still found correctly.

## `add_entry()`

Appends a new IP record to the cache file.

- Signature: `add_entry(array $ip): void`
- **Defensive Check**: First, verifies that `$ip['data']['ipAddress']` exists. If it is missing (e.g., an upstream error payload), it logs a warning and returns early, refusing to cache malformed data.
- Checks if the IP is already in the cache via `check_for_entry()`. If it is, it returns early to prevent duplicates.
- Appends a `added_at` Unix timestamp to the data payload.
- Saves the updated JSON array to the cache file without escaping slashes.
- Sends a Discord webhook notification for the successful cache addition.

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
