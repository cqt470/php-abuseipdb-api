# report.php

Usage documentation for [`src/api/report.php`](../../../src/api/report.php).

## Purpose

`report.php` exposes the report endpoint that validates input, sends a report to AbuseIPDB, and returns a fixed JSON response structure.

## Inclusion

The endpoint includes the shared include index:

```php
include_once __DIR__ . "/../includes/index.php";
```

## HTTP endpoint

- Method: `POST`
- Path: `/src/api/report.php`
- CORS headers are currently open for development.

## Required and optional parameters

Required JSON body parameters:

- `action` (string): must be `report`.
- `ip` (string): IPv4 address.
- `categories` (array): AbuseIPDB category IDs.
- `comment` (string): report comment text.
- `token` (string): must match `API_KEY_CUSTOM`.

Optional JSON body parameters:

- `timestamp` (string): ISO 8601 date-time. Defaults to server-side current timestamp.

## Exposed class and methods

- Class: `Zexa\Endpoints\ReportEndpoint`
- Constructor:
  - `__construct()`
- Internal action handler:
  - `report()`

## Minimal usage example

```bash
curl -X POST http://localhost:8000/api/report.php \
  -H "Content-Type: application/json" \
  -d '{"action":"report","ip":"8.8.8.8","categories":[15],"comment":"Suspicious traffic","token":"YOUR_CUSTOM_KEY"}'
```

## Response example

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

## Internal dependencies

- [`src/includes/index.php`](../../../src/includes/index.php)
- [`src/includes/endpoint.php`](../../../src/includes/endpoint.php)
- [`src/includes/security.php`](../../../src/includes/security.php)
- [`env.php`](../../../env.php)

## Known limitations and TODOs

- TODO in source: router integration is pending.
- CORS is open (`*`) and currently intended for development.
- Validation supports IPv4 only.
- No automatic retry logic if upstream AbuseIPDB report requests fail.
