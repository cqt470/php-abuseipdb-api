# PHP AbuseIPDB API

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![Release](https://img.shields.io/badge/release-v1.0.0--stable-green)

A self-hosted, zero-dependency PHP proxy for the AbuseIPDB API. The proxy
keeps the official AbuseIPDB key server-side and exposes one authenticated
endpoint for IP checks and reports.

> This document describes the 1.0.0 stable release. The source of truth for
> behavior is the code in [`src/`](./src/).

## Requirements

- PHP 8.1 or newer.
- HTTPS support in PHP for upstream requests.
- A writable `cache/ips.json` file.
- A writable `logs/` directory.
- No Composer, database, Redis, cURL, or framework is required.

## Installation

```bash
git clone https://github.com/cqt470/php-abuseipdb-api.git
cd php-abuseipdb-api
cp example.env.php env.php
mkdir -p cache logs
printf '[]' > cache/ips.json
```

Edit `env.php` before exposing the API. It is PHP code containing constants,
not a dotenv file, and must never be committed.

For local development:

```bash
php -S localhost:8000 -t src/
```

The endpoint is then available at
`http://localhost:8000/api/abuseipdb.php`. See
[`docs/DEPLOYMENT.md`](./docs/DEPLOYMENT.md) for production layout and
permissions.

## Configuration

All constants are defined in [`example.env.php`](./example.env.php).

| Constant | Example default | Purpose |
| --- | --- | --- |
| `API_KEY_ABUSEIPDB` | `YOUR_KEY_HERE` | Official key sent to AbuseIPDB |
| `API_KEY_CUSTOM` | `YOUR_KEY_HERE` | Token accepted by this proxy |
| `ENVIRONMENT` | `dev` | Skips token comparison only when exactly `dev` |
| `ENABLE_CACHING` | `true` | Enables cache reads for checks |
| `CACHE_TTL` | `60*60*12` | Cache lifetime in seconds |
| `CACHE_FILE` | `__DIR__."/cache/ips.json"` | Cache file path |
| `LOGS_DIR` | `__DIR__."/logs//"` | Log directory |
| `ABUSEIPDB_BASE_URL` | AbuseIPDB v2 URL | Upstream API base URL |
| `ENABLE_DISCORD_WEBHOOK` | `true` | Enables cache notifications |
| `URL_DISCORD_WEBHOOK` | `YOUR_KEY_HERE` | Discord webhook URL |
| `WEBHOOK_USERNAME` | `php-abuseipdb-api's Bot` | Discord username |
| `CORS_ALLOW_ORIGIN` | `*` | `Access-Control-Allow-Origin` value |
| `CORS_ALLOW_HEADERS` | `Content-Type, Authorization` | Allowed request headers |
| `SALT_SHA256` | `YOUR_KEY_HERE` | Salt used by `Security::sha256()` |
| `TRUSTED_PROXIES` | `[]` | Replaces the default Cloudflare CIDRs |

Set `ENVIRONMENT` to a value other than `dev` in production and configure a
specific CORS origin. The default `*` origin is intended only for development.

## API

The API accepts `POST /api/abuseipdb.php` with a non-empty JSON object. A token
is required in either `Authorization: Bearer <token>` or the body's `token`
field; the header takes precedence.

### Check an IP

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_CUSTOM_TOKEN" \
  -d '{"action":"check","ip":"8.8.8.8"}'
```

Required fields are `action` (`check`) and `ip` (IPv4 or IPv6). The response
contains the decoded AbuseIPDB check result under `parameters.info`.

### Report an IP

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_CUSTOM_TOKEN" \
  -d '{"action":"report","ip":"8.8.8.8","categories":[15],"comment":"Suspicious traffic"}'
```

Required fields are `action` (`report`), `ip`, `categories` (array), and
`comment` (string). `timestamp` is optional and must be ISO 8601 when supplied.
If omitted, the server's current ISO 8601 time is used. Reports are not cached.

### Response envelope

```json
{
  "message": "ok",
  "success": true,
  "parameters": {
    "info": {}
  }
}
```

`parameters` is omitted when no additional response data was added. Errors
use the same envelope with `success: false`; common statuses are 400 for
validation, 401 for a missing token, 403 for a rejected token, and 405 for a
non-POST request.

The complete request and error reference is
[`docs/src/api/abuseipdb.md`](./docs/src/api/abuseipdb.md).

## Runtime behavior

### Cache

Checks use `cache/ips.json` when `ENABLE_CACHING` is enabled. Entries contain
the upstream `data` fields plus `added_at`. Expired entries are removed during
lookups and trigger a gray `TTL Reached` Discord notification. New entries
trigger a green `New IP added to cache` notification. Cache rewrites do not use
file locking.

### Logging

The logger writes rows to `<LOGS_DIR>/<category>.log` using
`FILE_APPEND | LOCK_EX`. Rows include the uppercase priority, ISO 8601 time,
HTTP method, and message. Authentication failures include the resolved client
IP. There is no built-in rotation.

### Client IP resolution

`Security::get_ip()` trusts `HTTP_CF_CONNECTING_IP` and
`HTTP_X_FORWARDED_FOR` only when `REMOTE_ADDR` belongs to the configured
trusted proxy list. Invalid or missing `REMOTE_ADDR` falls back to
`127.0.0.1`.

## Documentation

- [API reference](./docs/src/api/abuseipdb.md)
- [Deployment guide](./docs/DEPLOYMENT.md)
- [Cache](./docs/src/includes/cache.md)
- [Endpoint base class](./docs/src/includes/endpoint.md)
- [Enums](./docs/src/includes/enums.md)
- [Include aggregator](./docs/src/includes/index.md)
- [Logger](./docs/src/includes/logger.md)
- [Security helpers](./docs/src/includes/security.md)
- [Discord webhook](./docs/src/includes/webhook.md)

## Limitations

- No application-level rate limiting.
- No automatic upstream retry.
- Cache writes are not locked.
- Webhook exceptions are not caught by current cache callers.
- `env.php` is loaded from the repository root; moving it outside the webroot
  is planned but not implemented.

## Testing

The repository includes PHPUnit tests and a local PHPUnit PHAR under `tools/`.

```bash
php tools/phpunit-10.phar
```

## Security

Do not commit `env.php`, cache data, logs, or real API keys. Restrict the
production document root and use a reverse proxy or firewall allowlist where
possible. IP addresses are personal data under GDPR Article 4(1), and may be
stored in the cache, logs, or Discord payloads.

Report sensitive vulnerabilities through GitHub Security Advisories. See
[`CONTRIBUTING.md`](./CONTRIBUTING.md) for contribution workflow.

## License

MIT. See [`LICENSE`](./LICENSE).
