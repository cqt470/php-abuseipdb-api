# Check action

Usage documentation for the `check` action exposed by [`src/api/abuseipdb.php`](../../src/api/abuseipdb.php).

## Purpose

The `check` action validates an IP address, retrieves its reputation data from AbuseIPDB through `Security::get_ip_info()`, and returns the upstream payload inside the standard JSON envelope. When caching is enabled, repeated lookups of the same IP are served from the local file cache until the entry expires.

## HTTP endpoint

- Method: `POST`
- Path: `/api/abuseipdb.php` (file: [`src/api/abuseipdb.php`](../../src/api/abuseipdb.php), served with `src/` as document root)
- Body: JSON object
- CORS is handled centrally by `Endpoint::handle_cors()` using the `CORS_ALLOW_ORIGIN` and `CORS_ALLOW_HEADERS` constants. `OPTIONS` preflight requests are answered with HTTP `204` before any authentication happens.

## Authentication

Authentication is performed in the `AbuseIPDBEndpoint` constructor before any action routing:

- The token is read from the `Authorization: Bearer <token>` header first, then falls back to the `token` body parameter.
- A missing token always produces HTTP `401` (`"Unauthenticated."`), even when `ENVIRONMENT` is `"dev"`.
- When `ENVIRONMENT` is not `"dev"`, the token is compared against `API_KEY_CUSTOM` with `hash_equals()`; a mismatch produces HTTP `403` (`"Unauthorized."`).
- When `ENVIRONMENT` is `"dev"`, the comparison is skipped and any present token is accepted.

## Parameters

Required JSON body parameters:

- `action` (string): must be `check`.
- `ip` (string): IP address to look up. Validated with `Security::validate_ip()`, which accepts IPv4 and IPv6 via `FILTER_VALIDATE_IP`.

Optional JSON body parameters:

- `token` (string): alternative to the `Authorization` header. If both are present, the header wins.

## Example request

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_CUSTOM_KEY" \
  -d '{"action":"check","ip":"8.8.8.8"}'
```

The same request with body-token authentication:

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -d '{"action":"check","ip":"8.8.8.8","token":"YOUR_CUSTOM_KEY"}'
```

## Example response

```json
{
  "message": "ok",
  "success": true,
  "parameters": {
    "info": {
      "data": {
        "ipAddress": "8.8.8.8"
      }
    }
  }
}
```

The `parameters.info` value is the raw decoded response returned by the AbuseIPDB API v2 `check` endpoint, wrapped in its own `data` object. The exact fields depend on the upstream API; `parameters.info.data` typically includes the IP address, the abuse confidence score, and related report metadata.

## Error responses

| HTTP code | Message | Condition |
| --- | --- | --- |
| 401 | `Unauthenticated.` | No token in header or body |
| 403 | `Unauthorized.` | Token does not match `API_KEY_CUSTOM` (non-dev) |
| 400 | `The IP doesn't seem to be valid.` | `ip` fails `Security::validate_ip()` |
| 400 | `The required field "ip" is missing.` | `ip` missing from body |
| 405 | `POST method required.` | Request method is not `POST` |

All error messages produced by the shared `Endpoint` base class are now in English.

## Caching behavior

- When `ENABLE_CACHING` is `true`, the result of every successful upstream lookup is stored in `cache/ips.json` with an `added_at` timestamp.
- Cached entries are reused until they are older than `CACHE_TTL` (default: 43200 seconds, 12 hours).
- Expired entries trigger eviction and a Discord webhook notification (title `TTL Reached`, color `#444444`), then the request falls through to a fresh AbuseIPDB lookup.
- New cache entries trigger a Discord webhook notification (title `New IP added to cache`, color `#2ecc71`).

## Internal dependencies

- [`src/includes/index.php`](../../src/includes/index.php)
- [`src/includes/endpoint.php`](../includes/endpoint.md)
- [`src/includes/security.php`](../includes/security.md)
- [`src/includes/cache.php`](../includes/cache.md)
- [`example.env.php`](../../example.env.php)

## Known limitations and TODOs

- No automatic retry if AbuseIPDB is unavailable; an upstream failure is returned as-is inside `parameters.info`.
- Upstream error responses may be cached for a full `CACHE_TTL` window because `Cache::add_entry()` is called unconditionally after the upstream call.
- `Endpoint::get_token()` emits a PHP warning when the `Authorization` header is absent (undefined array key) and relies on `getallheaders()`, which is not available under every SAPI. Body-token clients are unaffected functionally but may see warnings in logs.
