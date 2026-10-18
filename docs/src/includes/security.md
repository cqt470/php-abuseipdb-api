# security.php

Usage documentation for [`src/includes/security.php`](../../../src/includes/security.php).

## Purpose

`Security` provides static helpers for client IP retrieval, IP validation, and AbuseIPDB reputation lookups integrated with the local file cache.

## Inclusion

```php
include_once __DIR__ . "/index.php";
include_once __DIR__ . "/../../env.php";
```

In practice the file is loaded through the shared aggregator:

```php
include_once __DIR__ . "/../includes/index.php";
```

## Exposed class and public methods

- Class: `Zexa\Includes\Security`
- Public static methods:
  - `get_ip(): ?string`
  - `get_ip_info(?string $ip = null, bool $skip_cache = false): ?array`
  - `validate_ip(string $ip): bool`

### get_ip(): ?string

Resolves the client IP using the following order:

1. `REMOTE_ADDR` is read and validated. If it is missing or invalid, `127.0.0.1` is returned.
2. If `REMOTE_ADDR` is **not** a trusted proxy, it is returned as-is and proxy headers are ignored.
3. If `REMOTE_ADDR` **is** a trusted proxy, `HTTP_CF_CONNECTING_IP` is used when valid.
4. Otherwise, the first value of `HTTP_X_FORWARDED_FOR` is used when valid.
5. If no forwarded header is usable, `REMOTE_ADDR` (the proxy itself) is returned.

Trusted proxy ranges default to the official Cloudflare list (`Security::DEFAULT_TRUSTED_PROXIES`) and can be overridden by defining `TRUSTED_PROXIES` in `env.php`.

### `get_ip_info()`

```php
public static function get_ip_info(?string $ip = null, bool $skip_cache = false): ?array
```

Retrieves reputation data for an IP from the AbuseIPDB API v2 `check` endpoint.

- When `$ip` is `null`, the client IP is resolved with `get_ip()`.
- When `ENABLE_CACHING` is `true` and `$skip_cache` is `false`, a fresh cache hit is returned directly.
- On cache miss, a GET request is sent to `https://api.abuseipdb.com/api/v2/check` with the official key in both the `Key` request header and the `key` query parameter, a 16-second timeout, and `ignore_errors` enabled so 4xx/5xx bodies are still readable.
- The decoded response is stored in the cache through `Cache::add_entry()` before being returned.

Known caveats: upstream error responses are cached like successful ones, and the API key is included in the request URL, which may leak into server or proxy logs. Both are documented as limitations pending rework.

### `validate_ip()`

```php
public static function validate_ip(string $ip): bool
```

Wraps `filter_var($ip, FILTER_VALIDATE_IP)`. The filter accepts both IPv4 and IPv6 addresses, so validation is not IPv4-only despite what older documentation stated.

### is_trusted_proxy(string $ip): bool

Returns `true` if `$ip` falls inside any CIDR of the trusted proxy list (`TRUSTED_PROXIES` constant or `DEFAULT_TRUSTED_PROXIES`). Used by `get_ip()` to decide whether forwarding headers can be trusted.

### ip_in_cidr(string $ip, string $cidr): bool

Returns `true` if `$ip` is inside `$cidr`. Supports IPv4 and IPv6. When `$cidr` has no `/mask`, it is treated as a single host (mask 32 for IPv4, 128 for IPv6). Returns `false` on family mismatch or malformed input.

## Minimal usage example

```php
use Zexa\Includes\Security;

$info = Security::get_ip_info("8.8.8.8");
$isValid = Security::validate_ip("8.8.8.8");
$client = Security::get_ip();
```

## Internal dependencies

- [`src/includes/index.php`](./index.md)
- [`src/includes/cache.php`](./cache.md)
- [`example.env.php`](../../../example.env.php)

## Known limitations and TODOs

- No automatic retry when the AbuseIPDB call fails.
- Upstream error payloads are cached for a full `CACHE_TTL` window.
