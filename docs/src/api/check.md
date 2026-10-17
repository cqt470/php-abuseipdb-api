# Check action

Reference for the `check` action handled by [`src/api/abuseipdb.php`](../../../src/api/abuseipdb.php).

## Purpose

The `check` action validates the submitted IP and forwards a check request to AbuseIPDB through `Security::get_ip_info()`.

## Endpoint and routing

- Method: `POST`
- Path: `/api/abuseipdb.php`
- Required body field: `action` with value `check`

## Authentication

You must send a token using either:

- `Authorization` header with bearer token.
- `token` in JSON body.

When `ENVIRONMENT === "dev"`, token comparison is skipped, but token presence is still required.

## Request fields

- `action` (string, required): `check`
- `ip` (string, required): target IP
- `token` (string, optional if bearer header is used)

## Example

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: ******" \
  -d '{"action":"check","ip":"8.8.8.8"}'
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
