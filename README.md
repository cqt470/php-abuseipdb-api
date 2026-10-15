# PHP AbuseIPDB API

![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![GitHub last commit](https://img.shields.io/github/last-commit/cqt470/php-abuseipdb-api/master)
![GitHub stars](https://img.shields.io/github/stars/cqt470/php-abuseipdb-api)

A self-hosted PHP API proxy for AbuseIPDB with local JSON caching and optional Discord webhook notifications.

## About this project

- This project is experimental and in alpha status.
- No stable release is available yet.
- Main branch: `master`.
- Current repository status is early stage (0 forks, 0 stars, 0 stable releases at the time this documentation was written).
- This documentation and all .md files are AI-generated with human review. The source code is entirely hand-written by the author, with AI used only for minor bug fixing or testing.

## Why use this project

- Protects the official AbuseIPDB API key behind a custom proxy key.
- Reduces AbuseIPDB token usage through local caching.
- Sends Discord webhook notifications when a new IP is added to cache.
- Uses zero external dependencies (no Composer and no framework).
- Designed as a drop-in service that does not require large dependency trees.
- Designed for Cloudflare Workers and similar serverless architectures.

## Requirements

- PHP 8.1 or newer (developed and tested on PHP 8.4.26).
- OpenSSL-enabled PHP runtime for HTTPS requests through `file_get_contents`.
- Writable `/cache` directory for `cache/ips.json`.
- No database or Redis required.

## Installation

- Clone the repository:

```bash
git clone https://github.com/cqt470/php-abuseipdb-api.git
cd php-abuseipdb-api
```

- Copy the environment file:

```bash
cp example.env.php env.php
```

`env.php` is a native PHP constants file, not a `.env` file parsed by external libraries.

### Configuration variables

- `API_KEY_ABUSEIPDB`: Official AbuseIPDB API key.
- `API_KEY_CUSTOM`: Custom key used to protect this proxy API.
- `ENABLE_CACHING`: Keep `true` to reduce AbuseIPDB token usage.
- `ENABLE_DISCORD_WEBHOOK`: Enable or disable Discord webhook notifications.
- `URL_DISCORD_WEBHOOK`: Discord webhook URL.
- `WEBHOOK_USERNAME`: Discord webhook username.

## Start the server

### Development

Use the built-in PHP server for local development:

```bash
php -S localhost:8000 -t src/
```

### Production

- `php -S` is only for development.
- In production, use Apache or Nginx and serve files from `/src`.
- TODO: add complete production deployment documentation.

## API endpoints

| Endpoint | Method | Path | Description |
| --- | --- | --- | --- |
| Check | POST | `/src/api/check.php` | Check an IP on AbuseIPDB |
| Report | POST | `/src/api/report.php` | Report an IP to AbuseIPDB |

### Check endpoint

- Method: `POST`
- Path: `/src/api/check.php`
- Required parameters:
  - `action` (string, must be `check`)
  - `ip` (string, IPv4 currently supported)
  - `token` (string, must match `API_KEY_CUSTOM`)

#### cURL example

```bash
curl -X POST http://localhost:8000/api/check.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "check",
    "ip": "8.8.8.8",
    "token": "YOUR_CUSTOM_KEY"
  }'
```

#### JavaScript example

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

#### JSON response example

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

- Method: `POST`
- Path: `/src/api/report.php`
- Required parameters:
  - `action` (string, must be `report`)
  - `ip` (string, IPv4 currently supported)
  - `categories` (array)
  - `comment` (string)
  - `token` (string, must match `API_KEY_CUSTOM`)
- Optional parameters:
  - `timestamp` (string, ISO 8601, defaults to server current timestamp)

#### cURL example

```bash
curl -X POST http://localhost:8000/api/report.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "report",
    "ip": "8.8.8.8",
    "categories": [15],
    "comment": "Suspicious traffic",
    "token": "YOUR_CUSTOM_KEY"
  }'
```

#### JavaScript example

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

#### JSON response example

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

### cURL

- Use the cURL examples in the endpoint section for quick tests.

### JavaScript (fetch)

- Use `POST` with `Content-Type: application/json` and include `action`, `token`, and endpoint-specific fields.

### PHP (`file_get_contents`)

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

- Cache has no TTL. IP data remains until the file is modified or removed.
- CORS is open (`Access-Control-Allow-Origin: *`), suitable only for development.
- IP validation currently supports only IPv4.
- No server-side rate limiting.
- No automatic retry if AbuseIPDB is unavailable.
- No automated test suite configured. Current tests are manual HTML pages in `/test`.
- No stable release.

## Roadmap

- Add TTL support to cache.
- Add IPv6 validation support.
- Implement a unified router (currently each endpoint is a separate file).
- Replace the production section TODO with full deployment docs.
- Add bearer token authentication instead of token in request body.
- Add automated tests with PHPUnit.
- Add GitHub issue templates.
- Add SQL database support in a future version.

## Contributing

- See [`CONTRIBUTING.md`](./CONTRIBUTING.md).
- You can open an issue or submit a pull request.
- Issue templates are not available yet and are planned.

## Security

### Security notes

- API keys are stored in plain constants in [`env.php`](./env.php). This file is ignored by git in [`.gitignore`](./.gitignore), but it must never be committed.
- In production, prefer a secrets manager or environment-level secret injection.
- CORS is currently open and is a development limitation.
- Endpoint authentication currently uses a plain token in request body (`token == API_KEY_CUSTOM`). Keep this service behind a reverse proxy or IP allowlist.
- IP addresses are personal data under GDPR Article 4(1). This project stores IP data in [`cache/ips.json`](./cache/ips.json) and may send IP-related data through Discord webhook payloads.
- GDPR reference: https://gdpr-info.eu/art-4-gdpr/
- The codebase contains explicit WIP security TODOs in [`src/includes/security.php`](./src/includes/security.php), including robust IP source checks and potential string injection concerns. The project is not security audited.

### Security policy

- For sensitive vulnerabilities, use GitHub Security Advisories on this repository.
- If advisories are not available, contact the author at https://github.com/cqt470.

## License

- Licensed under the MIT License. See [`LICENSE`](./LICENSE).
- Commercial use and modification are allowed.
- Author attribution to Gioele Cairo (`cqt470`) must always be preserved.

## Internal documentation

- Endpoint docs: [`docs/src/api/check.md`](./docs/src/api/check.md), [`docs/src/api/report.md`](./docs/src/api/report.md)
- Include docs:
  - [`docs/src/includes/cache.md`](./docs/src/includes/cache.md)
  - [`docs/src/includes/endpoint.md`](./docs/src/includes/endpoint.md)
  - [`docs/src/includes/enums.md`](./docs/src/includes/enums.md)
  - [`docs/src/includes/index.md`](./docs/src/includes/index.md)
  - [`docs/src/includes/security.md`](./docs/src/includes/security.md)
  - [`docs/src/includes/webhook.md`](./docs/src/includes/webhook.md)
