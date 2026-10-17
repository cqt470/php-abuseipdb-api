# PHP AbuseIPDB API

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue)
![Status](https://img.shields.io/badge/status-alpha-orange)
![License](https://img.shields.io/badge/License-MIT-green)
![GitHub last commit](https://img.shields.io/github/last-commit/cqt470/php-abuseipdb-api/master)
![GitHub stars](https://img.shields.io/github/stars/cqt470/php-abuseipdb-api)

PHP AbuseIPDB API is a self-hosted proxy for AbuseIPDB, built with plain PHP and no Composer dependencies. The proxy keeps your official AbuseIPDB key on the server and exposes a controlled endpoint for `check` and `report` actions.

## Project status

The project is in **alpha** and the main development branch is `master`. Interfaces can still change.

## Why use this project

This project focuses on simple deployment and predictable behavior.

- No framework and no Composer dependencies.
- File-based cache (`cache/ips.json`) with TTL eviction.
- Optional Discord webhook notifications for cache events.
- One API endpoint with explicit `action` routing.

## Requirements

- PHP 8.1+
- OpenSSL-enabled PHP runtime (HTTPS requests via `file_get_contents`)
- Writable `cache/` directory
- No SQL database and no Redis

## Installation

```bash
git clone https://github.com/cqt470/php-abuseipdb-api.git
cd php-abuseipdb-api
cp example.env.php env.php
```

`env.php` is native PHP code with constants. It is not a dotenv file.

## Configuration

Constants are defined in `env.php` (see `example.env.php`):

- `API_KEY_ABUSEIPDB`: AbuseIPDB API key used for upstream requests.
- `API_KEY_CUSTOM`: Custom key used by clients against this proxy.
- `ENVIRONMENT`: `dev` skips token equality verification (but token presence is still required).
- `ENABLE_CACHING`: Enables local cache usage.
- `CACHE_TTL`: Cache entry lifetime in seconds.
- `ENABLE_DISCORD_WEBHOOK`: Enables webhook notifications.
- `URL_DISCORD_WEBHOOK`: Discord webhook URL.
- `WEBHOOK_USERNAME`: Discord webhook username.
- `CORS_ALLOW_ORIGIN`: Value for `Access-Control-Allow-Origin`.
- `CORS_ALLOW_HEADERS`: Value for `Access-Control-Allow-Headers`.

## Run locally

```bash
php -S localhost:8000 -t src/
```

The endpoint will be available at `http://localhost:8000/api/abuseipdb.php`.

## API

The API uses a single POST endpoint and an `action` field in the JSON body.

| Method | Path | Action | Description |
| --- | --- | --- | --- |
| POST | `/api/abuseipdb.php` | `check` | Check an IP against AbuseIPDB |
| POST | `/api/abuseipdb.php` | `report` | Report an IP to AbuseIPDB |

### Authentication

Authentication accepts either:

- `Authorization` header with bearer token.
- `token` in JSON body (legacy-compatible path).

In `ENVIRONMENT === "dev"`, token comparison is skipped, but a token must still be provided.

### Request examples

#### Check action

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: ******" \
  -d '{"action":"check","ip":"8.8.8.8"}'
```

#### Report action

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: ******" \
  -d '{"action":"report","ip":"8.8.8.8","categories":[15],"comment":"Suspicious traffic"}'
```

## Response shape

All responses use this envelope:

```json
{
  "message": "string or null",
  "success": true,
  "parameters": {
    "info": {}
  }
}
```

- `message`: human-readable message.
- `success`: `true` for HTTP 2xx, `false` otherwise.
- `parameters`: present only when extra payload is added.

## Cache behavior

Cache entries include `added_at` timestamps and are evicted after `CACHE_TTL`.

Webhook events currently used by cache logic:

- **New IP added to cache** (`#2ecc71`)
- **TTL Reached** (`#444444`)

## Known limitations

- `Security::get_ip()` trusts `HTTP_CF_CONNECTING_IP` when present (Cloudflare header spoofing hardening is still pending).
- The cache eviction path rewrites the cache file during `check_for_entry()` and is functional but not optimized.
- Report action does not retry failed upstream AbuseIPDB requests.
- No automated PHPUnit suite is configured yet.

## Internal documentation

- [`docs/src/api/check.md`](./docs/src/api/check.md)
- [`docs/src/api/report.md`](./docs/src/api/report.md)
- [`docs/src/includes/cache.md`](./docs/src/includes/cache.md)
- [`docs/src/includes/endpoint.md`](./docs/src/includes/endpoint.md)
- [`docs/src/includes/enums.md`](./docs/src/includes/enums.md)
- [`docs/src/includes/index.md`](./docs/src/includes/index.md)
- [`docs/src/includes/security.md`](./docs/src/includes/security.md)
- [`docs/src/includes/webhook.md`](./docs/src/includes/webhook.md)
- [`docs/includes/endpoint.md`](./docs/includes/endpoint.md)

## Contributing

See [`CONTRIBUTING.md`](./CONTRIBUTING.md).

## License

This project is distributed under MIT License. See [`LICENSE`](./LICENSE).
