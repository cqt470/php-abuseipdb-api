# AbuseIPDB endpoint

The API is exposed by [`src/api/abuseipdb.php`](../../../src/api/abuseipdb.php).
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
returned in `parameters.info` as `{"data": {...}}` and may be stored in
`cache/ips.json` when `ENABLE_CACHING` is enabled. Cached answers use the same
shape as live ones.

> **Breaking change in 1.2.0:** the raw `reports` list of the verbose AbuseIPDB
> answer (up to 100 reports, ~27 KB per IP) is no longer returned. It is
> replaced by `reportsSummary` (~1 KB), both in live answers and in the cache.
> Entries cached by older versions are summarized when read.

```json
"reportsSummary": {
  "sampleSize": 100,
  "categories": [
    { "id": 22, "name": "SSH",         "count": 91, "percent": 91 },
    { "id": 18, "name": "Brute-Force", "count": 74, "percent": 74 }
  ],
  "topCategory": { "id": 22, "name": "SSH", "percent": 91 },
  "timeline": { "last24h": 38, "last7d": 100, "last30d": 100, "oldest": "2026-11-19T22:03:11+00:00", "newest": "2026-11-23T07:41:02+00:00" },
  "topReporterCountries": [ { "code": "DE", "count": 27 }, { "code": "US", "count": 21 } ],
  "distinctReporters": 64
}
```

| Field | Description |
| --- | --- |
| `sampleSize` | Number of reports AbuseIPDB returned (at most 100, the most recent) |
| `categories` | Every category found, most frequent first; `percent` is the share of reports containing it, so the values can add up to more than 100 |
| `topCategory` | The first item of `categories`, or `null` without reports |
| `timeline` | Reports in the last 24 hours / 7 days / 30 days, and the oldest and newest report time (UTC) |
| `topReporterCountries` | The 5 countries with most reports |
| `distinctReporters` | Number of different reporters |

The timeline is computed when the answer is fetched from AbuseIPDB: a cached
answer keeps the counts of that moment (at most `CACHE_TTL` old).

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
| `categories` | array | AbuseIPDB category ids (integers 1-23, at least one) |
| `comment` | string | Report explanation |

Optional fields:

| Field | JSON type | Default |
| --- | --- | --- |
| `timestamp` | string | Current server time in ISO 8601 format |

`timestamp` must be a full ISO 8601 date-time with timezone, for example
`2026-11-23T08:55:19+01:00` or `2026-11-23T07:55:19Z`. Free text such as
`tomorrow` is rejected.

Duplicate categories are removed and the array is joined with commas and sent to AbuseIPDB's
`/report` endpoint. Reports are not cached.

With `ENABLE_QUEUE` on, the report is validated as above, then queued instead
of being sent: the answer is `202` with the message `queued` and
`parameters.ip` (the normalized IP). A report on an IP that is already queued
is merged into it: categories are joined and different comments concatenated
(up to 1024 characters). The cron script `src/cron/flush.php` sends the queue.

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
| 400 | `Specify at least one category.` | Empty `categories` |
| 400 | `Categories must be integers between 1 and 23.` | Unknown or non-integer category |
| 400 | `Invalid timestamp string (must be ISO 8601).` | Invalid report timestamp |
| 401 | `Unauthenticated.` | Missing token |
| 403 | `Unauthorized.` | Token rejected outside development |
| 500 | `Server misconfigured.` | `API_KEY_CUSTOM` empty or still `YOUR_KEY_HERE` outside development |
| 405 | `POST method required.` | Non-POST request |
| 4xx/5xx | `Got a <code> HTTP code from the API...` | AbuseIPDB answered with an error status, which is forwarded |
| 502 | `AbuseIPDB could not be reached.` | Network error or timeout towards AbuseIPDB |
| 500 | `The report queue is unavailable.` | `ENABLE_QUEUE` on, but the queue file cannot be written (missing `QUEUE_DIR`, corrupted file) |

With `ENABLE_QUEUE` on, a valid `report` answers `202` with the message
`queued` (not an error).

## Upstream behavior

When AbuseIPDB answers with a non-2xx status, both actions answer with
`success: false` and the same HTTP status (401 and 429 get a specific
message). For `report`, the decoded upstream error body is included in
`parameters.info`. Error responses from `check` are never cached.
