# security.php

Reference for [`src/includes/security.php`](../../../src/includes/security.php).

## Purpose

`Security` provides helper methods for client IP resolution, IP validation, and AbuseIPDB data retrieval.

## Public static methods

- `get_ip(): string`
- `get_ip_info(?string $ip = null, bool $skip_cache = false): ?array`
- `validate_ip(string $ip): bool`

## Method behavior

`get_ip()` resolves IP in this order:

1. `HTTP_CF_CONNECTING_IP` (if present and valid)
2. `REMOTE_ADDR` (if present and valid)
3. fallback `127.0.0.1`

`get_ip_info()`:

- loads cache from `cache/ips.json`
- optionally returns cached data when enabled
- performs an AbuseIPDB `check` request via `file_get_contents`
- stores retrieved data in cache

## Example

```php
use Zexa\Includes\Security;

$info = Security::get_ip_info("8.8.8.8");
$valid = Security::validate_ip("8.8.8.8");
```

## Dependencies

- [`src/includes/index.php`](../../../src/includes/index.php)
- [`src/includes/cache.php`](../../../src/includes/cache.php)
- `env.php` (runtime local file created from `example.env.php`)

## Known caveat

`get_ip()` currently trusts `HTTP_CF_CONNECTING_IP` whenever present and does not verify that `REMOTE_ADDR` belongs to Cloudflare ranges.
