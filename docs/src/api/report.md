# Report action

Usage documentation for the `report` action exposed by [`src/api/abuseipdb.php`](../../../src/api/abuseipdb.php).

## Purpose

The `report` action validates the incoming payload and forwards an abuse report to the AbuseIPDB API v2 `report` endpoint. It does not interact with the local cache: reports are always forwarded upstream.

## HTTP endpoint

- Method: `POST`
- Path: `/api/abuseipdb.php` (file: [`src/api/abuseipdb.php`](../../../src/api/abuseipdb.php), served with `src/` as document root)
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

- `action` (string): must be `report`.
- `ip` (string): IP address to report. Validated with `Security::validate_ip()`, which accepts IPv4 and IPv6 via `FILTER_VALIDATE_IP`.
- `categories` (array): AbuseIPDB category identifiers. The array is joined into a comma-separated string before being forwarded upstream.
- `comment` (string): report comment text forwarded to AbuseIPDB.

Optional JSON body parameters:

- `timestamp` (string): ISO 8601 date-time of the reported event. Defaults to the current server time (`date("c")`).
- `token` (string): alternative to the `Authorization` header. If both are present, the header wins.

## Example request

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_CUSTOM_KEY" \
  -d '{"action":"report","ip":"8.8.8.8","categories":[15],"comment":"Suspicious traffic"}'
```

The same request with body-token authentication:

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -d '{"action":"report","ip":"8.8.8.8","categories":[15],"comment":"Suspicious traffic","token":"YOUR_CUSTOM_KEY"}'
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

The `parameters.info` value is the raw decoded response returned by the AbuseIPDB API v2 `report` endpoint, wrapped in its own `data` object. The exact fields depend on the upstream API.

## Error responses

| HTTP code | Message | Condition |
| --- | --- | --- |
| 401 | `Unauthenticated.` | No token in header or body |
| 403 | `Unauthorized.` | Token does not match `API_KEY_CUSTOM` (non-dev) |
| 400 | `The IP doesn't seem to be valid.` | `ip` fails `Security::validate_ip()` |
| 400 | `Invalid timestamp string (must be ISO 8601).` | `timestamp` cannot be parsed by `DateTime` |
| 400 | `The required field "categories" is missing.` | Any required field missing from body |
| 405 | `POST method required.` | Request method is not `POST` |

All error messages produced by the shared `Endpoint` base class are now in English.

## Internal dependencies

- [`src/includes/index.php`](../../../src/includes/index.php)
- [`src/includes/endpoint.php`](../includes/endpoint.md)
- [`src/includes/security.php`](../includes/security.md)
- [`example.env.php`](../../../example.env.php)

## Known limitations and TODOs

- No automatic retry if the upstream AbuseIPDB report call fails; upstream failures are returned as-is inside `parameters.info`.
- Reports are not cached and do not interact with `cache/ips.json`.
- `Endpoint::get_token()` emits a PHP warning when the `Authorization` header is absent (undefined array key) and relies on `getallheaders()`, which is not available under every SAPI. Body-token clients are unaffected functionally but may see warnings in logs.
