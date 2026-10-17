# Report action

Reference for the `report` action handled by [`src/api/abuseipdb.php`](../../../src/api/abuseipdb.php).

## Purpose

The `report` action validates inputs and forwards a report request to AbuseIPDB.

## Endpoint and routing

- Method: `POST`
- Path: `/api/abuseipdb.php`
- Required body field: `action` with value `report`

## Authentication

You must send a token using either:

- `Authorization` header with bearer token.
- `token` in JSON body.

When `ENVIRONMENT === "dev"`, token comparison is skipped, but token presence is still required.

## Request fields

Required:

- `action` (string): `report`
- `ip` (string)
- `categories` (array)
- `comment` (string)

Optional:

- `timestamp` (string, ISO 8601)
- `token` (string, optional if bearer header is used)

## Example

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: ******" \
  -d '{"action":"report","ip":"8.8.8.8","categories":[15],"comment":"Suspicious traffic"}'
```

## Response shape

```json
{
  "message": "ok",
  "success": true,
  "parameters": {
    "info": {}
  }
}
```

## Dependencies

- [`src/includes/index.php`](../../../src/includes/index.php)
- [`src/includes/endpoint.php`](../../../src/includes/endpoint.php)
- [`src/includes/security.php`](../../../src/includes/security.php)
- `env.php` (runtime local file created from `example.env.php`)
