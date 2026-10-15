# PHP AbuseIPDB API

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![GitHub last commit](https://img.shields.io/github/last-commit/cqt470/php-abuseipdb-api/master)
![GitHub stars](https://img.shields.io/github/stars/cqt470/php-abuseipdb-api)

PHP AbuseIPDB API is a self-hosted proxy API for AbuseIPDB, written in PHP with a zero-dependency approach. It is designed to be dropped into a project quickly, while keeping your official AbuseIPDB key hidden behind a custom token.

## About this project

This repository is currently experimental and in alpha status. There is no stable release yet, and the main branch is `master`. At the time this documentation was prepared, the repository was still at an early stage with no stable release history.

This documentation and all .md files are AI-generated with human review. The source code is entirely hand-written by the author, with AI used only for minor bug fixing or testing.

## Why use this project

This project is meant for teams that want a simple and controlled proxy layer in front of AbuseIPDB. It focuses on practical deployment with minimal setup effort.

- It hides your official AbuseIPDB key behind a custom API key.
- It caches results locally to reduce AbuseIPDB token usage.
- It can send Discord webhook notifications when new IPs are added to cache.
- It avoids Composer and framework dependencies.
- It is designed for serverless and edge integration patterns, including Cloudflare Workers.

## Requirements

The codebase requires modern PHP because it uses enums and typed features. It was developed and tested on PHP 8.4.26.

- PHP 8.1 or newer.
- OpenSSL-enabled PHP runtime for HTTPS calls via `file_get_contents`.
- Writable `/cache` directory for `cache/ips.json`.
- No SQL database and no Redis are required in the current design.

## Installation

Clone the repository and prepare local configuration by creating `env.php` from the provided example.

```bash
git clone https://github.com/cqt470/php-abuseipdb-api.git
cd php-abuseipdb-api
cp example.env.php env.php
```

The configuration file is native PHP code with constants. It is not a `.env` file managed by external parsers.

### Configuration variables

The following constants are defined in `env.php`.

- `API_KEY_ABUSEIPDB`: Official AbuseIPDB API key.
- `API_KEY_CUSTOM`: Custom key used to authorize requests to this proxy.
- `ENABLE_CACHING`: Recommended to keep `true` for lower AbuseIPDB token usage.
- `ENABLE_DISCORD_WEBHOOK`: Enables or disables Discord notifications.
- `URL_DISCORD_WEBHOOK`: Discord webhook URL.
- `WEBHOOK_USERNAME`: Display name for webhook messages.

## Start the server

For local development, use the PHP built-in server from the repository root.

```bash
php -S localhost:8000 -t src/
```

For production, use a real web server such as Apache or Nginx and serve files from `/src`. The built-in PHP server is only for development, and full production deployment instructions are still a TODO.

## API endpoints

The API currently exposes two POST endpoints, one for checks and one for reports.

| Endpoint | Method | Path | Description |
| --- | --- | --- | --- |
| Check | POST | `/src/api/check.php` | Check an IP on AbuseIPDB |
| Report | POST | `/src/api/report.php` | Report an IP to AbuseIPDB |

### Check endpoint

The check endpoint validates the incoming IPv4 value and retrieves AbuseIPDB data through internal helpers and cache logic.

Required parameters:

- `action` (string, value `check`)
- `ip` (string)
- `token` (string, must match `API_KEY_CUSTOM`)

Example cURL request:

```bash
curl -X POST http://localhost:8000/api/check.php \
  -H "Content-Type: application/json" \
  -d '{"action":"check","ip":"8.8.8.8","token":"YOUR_CUSTOM_KEY"}'
```

Example JavaScript request:

```js
await fetch("http://localhost:8000/api/check.php", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    action: "check",
    ip: "8.8.8.8",
    token: "YOUR_CUSTOM_KEY"
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
      "ipAddress": "8.8.8.8"
    }
  }
}
```

### Report endpoint

The report endpoint forwards report payloads to AbuseIPDB after local validation.

Required parameters:

- `action` (string, value `report`)
- `ip` (string)
- `categories` (array)
- `comment` (string)
- `token` (string, must match `API_KEY_CUSTOM`)

Optional parameters:

- `timestamp` (string, ISO 8601). If missing, server time is used.

Example cURL request:

```bash
curl -X POST http://localhost:8000/api/report.php \
  -H "Content-Type: application/json" \
  -d '{"action":"report","ip":"8.8.8.8","categories":[15],"comment":"Suspicious traffic","token":"YOUR_CUSTOM_KEY"}'
```

Example JavaScript request:

```js
await fetch("http://localhost:8000/api/report.php", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    action: "report",
    ip: "8.8.8.8",
    categories: [15],
    comment: "Suspicious traffic",
    token: "YOUR_CUSTOM_KEY"
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

## Integration examples

In addition to cURL and JavaScript, you can integrate the proxy directly from PHP.

```php
$payload = json_encode([
    "action" => "check",
    "ip" => "8.8.8.8",
    "token" => "YOUR_CUSTOM_KEY"
]);

$context = stream_context_create([
    "http" => [
        "method" => "POST",
        "header" => "Content-Type: application/json\r\n",
        "content" => $payload,
        "ignore_errors" => true,
    ]
]);

$response = file_get_contents("http://localhost:8000/api/check.php", false, $context);
```

## Known limitations

This project is intentionally minimal and still under active iteration. The following limitations are known and documented.

- Cache has no TTL.
- CORS is open (`Access-Control-Allow-Origin: *`) and suitable only for development.
- IP validation currently supports IPv4 only.
- No server-side rate limiting.
- No automatic retry if AbuseIPDB is unavailable.
- No automated PHPUnit suite is configured.
- Current manual tests are HTML pages in `/test`.

## Roadmap

The roadmap is focused on hardening, maintainability, and production readiness.

- Add TTL support to cache.
- Add IPv6 validation support.
- Implement a unified router.
- Replace production setup TODO with complete deployment guidance.
- Add bearer token authentication instead of body token.
- Add automated tests with PHPUnit.
- Add issue templates.
- Add SQL database support in a future version.

## Security

### Security notes

The current security model is intentionally simple and should be treated as WIP.

- API keys are plain constants in [`env.php`](./env.php), which is ignored in [`.gitignore`](./.gitignore).
- `env.php` must never be committed.
- In production, secrets managers or environment-injected secrets are recommended.
- Endpoint auth is currently `token == API_KEY_CUSTOM` in request body.
- A reverse proxy and IP allowlist are strongly recommended in production.
- IP addresses are personal data under GDPR Article 4(1).
- IPs are persisted in [`cache/ips.json`](./cache/ips.json) and can appear in Discord webhook payloads.
- GDPR reference: https://gdpr-info.eu/art-4-gdpr/
- Security-related TODOs are present in [`src/includes/security.php`](./src/includes/security.php), including more robust checks and potential string injection concerns.

### Security policy

Report sensitive vulnerabilities through GitHub Security Advisories for this repository. If advisories are unavailable, contact the author through https://github.com/cqt470.

## Contributing

Please read [`CONTRIBUTING.md`](./CONTRIBUTING.md) for workflow and contribution scope. Contributions are welcome through issues and pull requests.

## License

This project is distributed under the MIT License. See [`LICENSE`](./LICENSE) for full text.

Commercial use and modification are allowed, but attribution to Gioele Cairo (`cqt470`) must always be preserved.

## Internal documentation

Detailed source documentation is available in:

- [`docs/src/api/check.md`](./docs/src/api/check.md)
- [`docs/src/api/report.md`](./docs/src/api/report.md)
- [`docs/src/includes/cache.md`](./docs/src/includes/cache.md)
- [`docs/src/includes/endpoint.md`](./docs/src/includes/endpoint.md)
- [`docs/src/includes/enums.md`](./docs/src/includes/enums.md)
- [`docs/src/includes/index.md`](./docs/src/includes/index.md)
- [`docs/src/includes/security.md`](./docs/src/includes/security.md)
- [`docs/src/includes/webhook.md`](./docs/src/includes/webhook.md)
