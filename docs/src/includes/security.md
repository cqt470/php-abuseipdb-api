# Security helpers

[`src/includes/security.php`](../../../src/includes/security.php) contains static
helpers for client IP resolution, IP validation, and AbuseIPDB lookups.

## Public methods

```php
get_ip(): ?string
validate_ip(string $ip): bool
is_trusted_proxy(string $ip): bool
ip_in_cidr(string $ip, string $cidr): bool
get_ip_info(?string $ip = null, bool $skip_cache = false): array|int
sha256(string $input, ?string $custom_salt = null): string
normalize_ip(string $ip): string
```

`get_ip()` returns `REMOTE_ADDR` unless it is a trusted proxy. Only then does
it consider a valid `HTTP_CF_CONNECTING_IP` or the first valid value in
`HTTP_X_FORWARDED_FOR`. Missing or invalid `REMOTE_ADDR` falls back to
`127.0.0.1`.

The default trusted ranges are Cloudflare's published IPv4 and IPv6 CIDRs.
Defining `TRUSTED_PROXIES` replaces the default list.

`get_ip_info()` checks the file cache when enabled, otherwise sends a GET
request to `ABUSEIPDB_BASE_URL/check?ipAddress=<ip>&verbose` with the official
key in the `Key` header. Only HTTP 200 is accepted for caching and return;
other statuses are logged and the HTTP code is returned as an integer. The
request timeout is 16 seconds.

The address is normalized with `normalize_ip()` (lowercase, compressed IPv6,
the form AbuseIPDB returns) before the cache lookup, so different spellings of
the same IPv6 address share one cache entry.

Cache hits and live responses have the same shape, `{"data": {...}}`; the
internal `added_at` field is not exposed. A cache hit costs one read of the
cache file (`Cache::find_entry()`).

The raw `reports` list of a live answer is replaced by `reportsSummary` (see
[`ReportSummary`](./report_summary.md)) before it is cached and returned.
Cache entries written by older versions, which still contain `reports`, are
summarized when read.

`sha256()` hashes the input concatenated with `SALT_SHA256`, or with the
explicit salt supplied by the caller.
