# PHP AbuseIPDB API

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![GitHub last commit](https://img.shields.io/github/last-commit/cqt470/php-abuseipdb-api)
![GitHub stars](https://img.shields.io/github/stars/cqt470/php-abuseipdb-api)
![Release](https://img.shields.io/badge/release-v1.0.0--alpha-orange)

PHP AbuseIPDB API is a self-hosted proxy API for AbuseIPDB, written in PHP with a zero-dependency approach. It is designed to be dropped into a project quickly, while keeping your official AbuseIPDB key hidden behind a custom token.

## About this project

This repository is currently experimental and in alpha status. The upcoming release is `v1.0.0-alpha`, and the main branch is `master`. There is no stable release yet.

This documentation and all .md files are AI-generated with human review. The source code is entirely hand-written by the author, with AI used only for minor bug fixing or testing.

## Why use this project

This project is meant for teams that want a simple and controlled proxy layer in front of AbuseIPDB. It focuses on practical deployment with minimal setup effort.

- It hides your official AbuseIPDB key behind a custom API key.
- It caches results locally with TTL-based eviction to reduce AbuseIPDB token usage.
- It can send Discord webhook notifications when new IPs are added to cache and when cached entries expire.
- It accepts authentication either as an `Authorization: Bearer` header or as a body token.
- It centralizes CORS handling through configuration constants.
- It avoids Composer and framework dependencies.
- It is designed for serverless and edge integration patterns, including Cloudflare Workers.

## Requirements

The codebase requires modern PHP because it uses enums and typed features. It was developed and tested on PHP 8.4.26.

- PHP 8.1 or newer.
- OpenSSL-enabled PHP runtime for HTTPS calls via `file_get_contents`.
- A writable `cache/ips.json` file for the file-based cache.
- A writable `logs/` directory for log files.
- No SQL database and no Redis are required in the current design.

## Installation

Clone the repository and prepare local configuration by creating `env.php` from the provided example. The cache file and the logs directory are gitignored and must be created manually.

```bash
git clone https://github.com/cqt470/php-abuseipdb-api.git
cd php-abuseipdb-api
cp example.env.php env.php
mkdir -p cache logs
echo "[]" > cache/ips.json
```

The configuration file is native PHP code with constants. It is not a `.env` file managed by external parsers.

### Configuration variables

The following constants are defined in `env.php`.

| Constant | Default (example) | Description |
| --- | --- | --- |
| `API_KEY_ABUSEIPDB` | `YOUR_KEY_HERE` | Official AbuseIPDB API key used for upstream calls. |
| `API_KEY_CUSTOM` | `YOUR_KEY_HERE` | Custom key used to authorize requests to this proxy. |
| `ENVIRONMENT` | `dev` | When set to `"dev"`, token verification against `API_KEY_CUSTOM` is skipped. A token is still required. |
| `ENABLE_CACHING` | `true` | Enables the local file cache. Recommended to keep `true` for lower AbuseIPDB token usage. |
| `CACHE_TTL` | `60*60*12` | Cache entry lifetime in seconds (default 43200, 12 hours). Expired entries are evicted on lookup. |
| `ENABLE_DISCORD_WEBHOOK` | `true` | Enables or disables Discord notifications. |
| `URL_DISCORD_WEBHOOK` | `YOUR_KEY_HERE` | Discord webhook URL. |
| `WEBHOOK_USERNAME` | `php-abuseipdb-api's Bot` | Display name for webhook messages. |
| `CORS_ALLOW_ORIGIN` | `*` | Value sent in `Access-Control-Allow-Origin`. Configure it for production; `*` is highly insecure. |
| `CORS_ALLOW_HEADERS` | `Content-Type, Authorization` | Value sent in `Access-Control-Allow-Headers`. |

## Start the server

For local development, use the PHP built-in server from the repository root with `src/` as document root.

```bash
php -S localhost:8000 -t src/
```

With this setup the API is reachable at `http://localhost:8000/api/abuseipdb.php`. For production, use a real web server such as Apache or Nginx and serve files from `/src`. The built-in PHP server is only for development; complete production deployment guidance is planned for a future update.

## API endpoints

The API exposes a single POST endpoint that dispatches on the `action` body parameter.

| Action | Method | Path | Description |
| --- | --- | --- | --- |
| `check` | POST | `/api/abuseipdb.php` | Check an IP on AbuseIPDB |
| `report` | POST | `/api/abuseipdb.php` | Report an IP to AbuseIPDB |

### Authentication

Every request must carry a token, either as an `Authorization: Bearer <token>` header or as a `token` field in the JSON body. When both are present, the header wins.

- A missing token always produces HTTP `401` (`"Unauthenticated."`), even in development.
- When `ENVIRONMENT` is not `"dev"`, the token is compared against `API_KEY_CUSTOM` using a timing-safe `hash_equals()` comparison; a mismatch produces HTTP `403` (`"Unauthorized."`).
- When `ENVIRONMENT` is `"dev"`, the comparison is skipped and any present token is accepted. Never deploy with `ENVIRONMENT` set to `"dev"`.

### Check action

The `check` action validates the incoming IP and retrieves AbuseIPDB data through internal helpers and cache logic.

Required parameters:

- `action` (string, value `check`)
- `ip` (string)

Example cURL request:

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_CUSTOM_KEY" \
  -d '{"action":"check","ip":"8.8.8.8"}'
```

Example JavaScript request:

```js
await fetch("http://localhost:8000/api/abuseipdb.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "Authorization": "Bearer YOUR_CUSTOM_KEY"
  },
  body: JSON.stringify({
    action: "check",
    ip: "8.8.8.8"
  })
});
```

Example response:

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

The `parameters.info` value is the raw decoded response of the AbuseIPDB API v2 `check` endpoint, wrapped in its own `data` object. Exact fields depend on the upstream API and typically include the abuse confidence score and report metadata.

### Report action

The `report` action forwards report payloads to AbuseIPDB after local validation. Reports are not cached.

Required parameters:

- `action` (string, value `report`)
- `ip` (string)
- `categories` (array of AbuseIPDB category identifiers)
- `comment` (string)

Optional parameters:

- `timestamp` (string, ISO 8601). If missing, server time is used.

Example cURL request:

```bash
curl -X POST http://localhost:8000/api/abuseipdb.php \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_CUSTOM_KEY" \
  -d '{"action":"report","ip":"8.8.8.8","categories":[15],"comment":"Suspicious traffic"}'
```

Example JavaScript request:

```js
await fetch("http://localhost:8000/api/abuseipdb.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "Authorization": "Bearer YOUR_CUSTOM_KEY"
  },
  body: JSON.stringify({
    action: "report",
    ip: "8.8.8.8",
    categories: [15],
    comment: "Suspicious traffic"
  })
});
```

Example response:

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

### Response envelope

All responses share the same envelope:

- `message`: human-readable string or `null`.
- `success`: `true` for HTTP 2xx codes, `false` otherwise.
- `parameters`: present only when the endpoint attaches data, such as the `info` payload described above.

### Error responses

| HTTP code | Message | Condition |
| --- | --- | --- |
| 401 | `Unauthenticated.` | No token in header or body |
| 403 | `Unauthorized.` | Token does not match `API_KEY_CUSTOM` (non-dev) |
| 405 | `POST method required.` | Request method is not `POST` |
| 400 | `The IP doesn't seem to be valid.` | `ip` fails IP validation |
| 400 | `Invalid timestamp string (must be ISO 8601.` | `timestamp` not parseable as ISO 8601 (report action) |
| 400 | `Il campo <name> non è stato trovato.` | Required body field missing |
| 400 | `"<name>" should be of datatype "<type>", "<actual>" received.` | Body field of the wrong JSON type |
| 400 | `Specificare un'azione valida.` / `Azione non consentita.` | Missing or unregistered `action` |

All error messages produced by the shared `Endpoint` base class are now in English.

## Caching

When `ENABLE_CACHING` is `true`, results of `check` lookups are stored in `cache/ips.json` with an `added_at` timestamp and reused until they become older than `CACHE_TTL` (12 hours by default). Expired entries are evicted on lookup and trigger a Discord notification (title `TTL Reached`, gray), while new entries trigger a green `New IP added to cache` notification. Cache eviction and file rewrite behavior have known inefficiencies described in [the cache documentation](./docs/src/includes/cache.md).

## Logging

Endpoints write categorized, timestamped rows to plain-text files under `logs/`, for example `logs/auth.log`. Failed authentication attempts are currently logged at `debug` priority with the client IP included. See [the logger documentation](./docs/src/includes/logger.md) for the row format.

## Integration examples

In addition to cURL and JavaScript, you can integrate the proxy directly from PHP.

```php
$payload = json_encode([
    "action" => "check",
    "ip" => "8.8.8.8"
]);

$context = stream_context_create([
    "http" => [
        "method" => "POST",
        "header" => "Content-Type: application/json\r\nAuthorization: Bearer YOUR_CUSTOM_KEY\r\n",
        "content" => $payload,
        "ignore_errors" => true,
    ]
]);

$response = file_get_contents("http://localhost:8000/api/abuseipdb.php", false, $context);
```

## Known limitations

This project is intentionally minimal and still under active iteration. The following limitations are known and documented:

- No server-side rate limiting.
- No automatic retry if AbuseIPDB is unavailable.
- Cache and log writes do not use file locking.

## Roadmap

The roadmap is focused on hardening, maintainability, and production readiness.

- [x] Verify that the request truly originates from Cloudflare before trusting `HTTP_CF_CONNECTING_IP`.
- [ ] Implement a dedicated front controller with a route table.
- [ ] Move `env.php` outside the webroot with a configurable path.
- [x] Replace the hardcoded body-token comparison caveats with full Bearer-only auth once backward compatibility is dropped.
- [x] Add automated tests with PHPUnit.
- [ ] Add issue templates.
- [ ] Add SQL database support in a future version.
- [ ] Add a closed-loop honeypot as a future enhancement.

## Documentation

Detailed source documentation is available in:

- [Check action reference](./docs/src/api/check.md)
- [Report action reference](./docs/src/api/report.md)
- [Endpoint base class](./docs/src/includes/endpoint.md)
- [Security helpers](./docs/src/includes/security.md)
- [Cache](./docs/src/includes/cache.md)
- [Webhook](./docs/src/includes/webhook.md)
- [Logger](./docs/src/includes/logger.md)
- [Enums](./docs/src/includes/enums.md)
- [Include aggregator](./docs/src/includes/index.md)

## Security

### Security notes

The current security model is intentionally simple and should be treated as WIP.

- API keys are plain constants in `env.php`, which is ignored in [`.gitignore`](./.gitignore).
- `env.php` must never be committed.
- In production, secrets managers or environment-injected secrets are recommended.
- Endpoint auth compares the provided token against `API_KEY_CUSTOM` with a timing-safe `hash_equals()`; the comparison is skipped in `dev` environments.
- Proxy headers (`HTTP_CF_CONNECTING_IP`, `HTTP_X_FORWARDED_FOR`) are only trusted when `REMOTE_ADDR` belongs to a trusted proxy. The default list is the official Cloudflare ranges; override it by defining `TRUSTED_PROXIES` in `env.php`.
- A reverse proxy and IP allowlist are strongly recommended in production.
- IP addresses are personal data under GDPR Article 4(1).
- IPs are persisted in `cache/ips.json` and can appear in Discord webhook payloads.
- GDPR reference: https://gdpr-info.eu/art-4-gdpr/

### Security policy

Report sensitive vulnerabilities through GitHub Security Advisories for this repository. If advisories are unavailable, contact the author through https://github.com/cqt470.

## Contributing

Please read [`CONTRIBUTING.md`](./CONTRIBUTING.md) for workflow and contribution scope. Contributions are welcome through issues and pull requests.

## License

This project is distributed under the MIT License. See [`LICENSE`](./LICENSE) for full text.

Commercial use and modification are allowed, but attribution to Gioele Cairo (`cqt470`) must always be preserved.
