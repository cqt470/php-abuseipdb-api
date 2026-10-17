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

## `get_ip()`

Returns the best-guess real client IP.

1. If `$_SERVER["HTTP_CF_CONNECTING_IP"]` is present and passes `validate_ip()`, it is returned (Cloudflare integration).
2. Otherwise, if `$_SERVER["REMOTE_ADDR"]` is present and passes `validate_ip()`, it is returned.
3. As a last resort, the literal `"127.0.0.1"` is returned.

Both header branches validate in the affirmative direction (`if(Security::validate_ip($ip)) return $ip;`): the historically inverted `!` was fixed in commit `c75b106` and must not be reintroduced.

Known caveat: the Cloudflare header is trusted unconditionally whenever it is present. Verifying that `REMOTE_ADDR` belongs to Cloudflare's published IP ranges is a planned hardening item and is the last open Tier 0 security fix.

## `get_ip_info()`

```php
public static function get_ip_info(?string $ip = null, bool $skip_cache = false): ?array
```

Retrieves reputation data for an IP from the AbuseIPDB API v2 `check` endpoint.

- When `$ip` is `null`, the client IP is resolved with `get_ip()`.
- When `ENABLE_CACHING` is `true` and `$skip_cache` is `false`, a fresh cache hit is returned directly.
- On cache miss, a GET request is sent to `https://api.abuseipdb.com/api/v2/check` with the official key in both the `Key` request header and the `key` query parameter, a 16-second timeout, and `ignore_errors` enabled so 4xx/5xx bodies are still readable.
- The decoded response is stored in the cache through `Cache::add_entry()` before being returned.

Known caveats: upstream error responses are cached like successful ones, and the API key is included in the request URL, which may leak into server or proxy logs. Both are documented as limitations pending rework.

## `validate_ip()`

```php
public static function validate_ip(string $ip): bool
```

Wraps `filter_var($ip, FILTER_VALIDATE_IP)`. The filter accepts both IPv4 and IPv6 addresses, so validation is not IPv4-only despite what older documentation stated.

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

- `HTTP_CF_CONNECTING_IP` spoofing is possible when the deployment is not behind Cloudflare or when `REMOTE_ADDR` is not checked against Cloudflare IP ranges (planned Tier 0 fix #3).
- No automatic retry when the AbuseIPDB call fails.
- Upstream error payloads are cached for a full `CACHE_TTL` window.
