# AbuseIPDB endpoint

The API is exposed by [`src/api/abuseipdb.php`](../../src/api/abuseipdb.php).
It is one `POST` endpoint selected by the `action` field:

```text
/api/abuseipdb.php
```

Every request must contain a non-empty token. The endpoint first checks the
`Authorization` header for an RFC 6750 Bearer token and then falls back to the
JSON body's `token` field. The header wins when both are supplied. In
`ENVIRONMENT=dev`, any supplied token is accepted; in other environments it
must match `API_KEY_CUSTOM` using `hash_equals()`.

## Request format

The body must be a non-empty JSON object. Requests must use `POST`.

### `check`

Required fields:

| Field | JSON type | Description |
| --- | --- | --- |
| `action` | string | `check` |
| `ip` | string | IPv4 or IPv6 address |

The action calls `Security::get_ip_info()`. Successful upstream responses are
returned in `parameters.info` and may be stored in `cache/ips.json` when
`ENABLE_CACHING` is enabled.

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_CUSTOM_TOKEN" \
  -d '{"action":"check","ip":"8.8.8.8"}'
```

### `report`

Required fields:

| Field | JSON type | Description |
| --- | --- | --- |
| `action` | string | `report` |
| `ip` | string | IPv4 or IPv6 address |
| `categories` | array | AbuseIPDB category values |
| `comment` | string | Report explanation |

Optional fields:

| Field | JSON type | Default |
| --- | --- | --- |
| `timestamp` | string | Current server time in ISO 8601 format |

The categories array is joined with commas and sent to AbuseIPDB's
`/report` endpoint. Reports are not cached.

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_CUSTOM_TOKEN" \
  -d '{"action":"report","ip":"8.8.8.8","categories":[15],"comment":"Suspicious traffic"}'
```

## Response envelope

Responses are JSON:

```json
{
  "message": "ok",
  "success": true,
  "parameters": {
    "info": {}
  }
}
```

`parameters` is included only when the endpoint adds response data. The
`info` value is the decoded upstream response and its exact fields depend on
AbuseIPDB.

## Errors

| Status | Message | Cause |
| --- | --- | --- |
| 400 | `The body must be a valid body.` | Invalid JSON or a non-object JSON value |
| 400 | `A body is needed.` | Empty body |
| 400 | `Specify a valid action.` | Missing or empty `action` |
| 400 | `Action not permitted.` | Unknown action |
| 400 | `The required field "<name>" is missing.` | Missing required action field |
| 400 | `"<name>" should be of datatype "<type>", "<actual>" received.` | Wrong JSON type |
| 400 | `The IP doesn't seem to be valid.` | Invalid IP address |
| 400 | `Invalid timestamp string (must be ISO 8601).` | Invalid report timestamp |
| 401 | `Unauthenticated.` | Missing token |
| 403 | `Unauthorized.` | Token rejected outside development |
| 405 | `POST method required.` | Non-POST request |

## Upstream behavior

The endpoint forwards the decoded upstream response even when the upstream
request reports an error. The `check` helper returns `null` for non-200 status
codes; the `report` action does not perform a local HTTP status check.
