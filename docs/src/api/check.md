# check.php

Usage documentation for [`src/api/check.php`](../../../src/api/check.php).

## Purpose

`check.php` exposes the check endpoint that validates an IPv4 address, queries AbuseIPDB through internal utilities, and returns a fixed JSON response structure.

## Inclusion

The endpoint includes the shared include index:

```php
include_once __DIR__ . "/../includes/index.php";
```

## HTTP endpoint

- Method: `POST`
- Path: `/src/api/check.php`
- CORS headers are currently open for development.

## Required and optional parameters

Required JSON body parameters:

- `action` (string): must be `check`.
- `ip` (string): IPv4 address.
- `token` (string): must match `API_KEY_CUSTOM`.

Optional parameters:

- None.

## Exposed class and methods

- Class: `Zexa\Endpoints\CheckEndpoint`
- Constructor:
  - `__construct()`
- Internal action handler:
  - `check()`

## Minimal usage example

```bash
curl -X POST http://localhost:8000/api/check.php \
  -H "Content-Type: application/json" \
  -d '{"action":"check","ip":"8.8.8.8","token":"YOUR_CUSTOM_KEY"}'
```

## Response example

```json
{
  "message": "ok",
  "success": true,
  "parameters": {
    "info": {
      "ipAddress": "8.8.8.8"
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
- Validation supports IPv4 only because it relies on [`src/includes/security.php`](../../../src/includes/security.php).
