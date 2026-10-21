# Copilot instructions for `php-abuseipdb-api`

> Last reviewed: 2026-10-21. Treat the PHP source as authoritative and keep
> this file and the documentation synchronized whenever the public behavior,
> configuration, or file layout changes.

## Project facts

- Self-hosted PHP proxy for AbuseIPDB; 1.0.0 stable release.
- PHP 8.1+; no Composer dependencies, framework, database, Redis, cURL, or
  Guzzle.
- Namespaces are `Zexa\Includes` and `Zexa\Endpoints`.
- The public API is one `POST /api/abuseipdb.php` endpoint with `check` and
  `report` actions.
- Runtime state is `cache/ips.json`; logs are plain files under `logs/`.
- Configuration is PHP constants in root `env.php`, copied from
  `example.env.php`. The planned external configuration path is not
  implemented.

## Current layout

```text
src/api/abuseipdb.php
src/includes/{cache,endpoint,enums,index,logger,security,webhook}.php
docs/src/api/abuseipdb.md
docs/src/includes/{cache,endpoint,enums,index,logger,security,webhook}.md
tests/Unit/
```

Do not reintroduce `docs/src/api/check.md` or `docs/src/api/report.md`. The
canonical documentation directories are `docs/src/api/` and
`docs/src/includes/`.

## Code conventions

- Use PSR-12 style, four spaces, English identifiers and comments.
- Add parameter, property, and return types. Use `mixed` only where required
  by the dynamic JSON API.
- Endpoint classes are `final`, extend `Endpoint`, and instantiate themselves
  at the bottom of their file.
- Load shared code through `src/includes/index.php` and use `__DIR__`-relative
  include paths.
- Use `file_get_contents()` and `stream_context_create()` for HTTP. Do not add
  cURL, Guzzle, or another dependency.
- Send endpoint responses through `Endpoint::send_success()` or
  `Endpoint::send_error()`. Preserve the JSON envelope:

  ```json
  {"message": "string or null", "success": true, "parameters": {}}
  ```

- Do not add direct debug output (`echo`, `print`, `var_dump`, or `die`) to
  committed endpoint code. The existing response serializer is the intended
  output path.
- The `log_priority` enum cases are `debug`, `info`, `warning`, `error`, and
  `critical`.
- `Logger::log()` uses `FILE_APPEND | LOCK_EX`; retain this concurrency
  guarantee.

## Configuration

The constants currently defined by `example.env.php` are:

`API_KEY_ABUSEIPDB`, `API_KEY_CUSTOM`, `ENVIRONMENT`, `ENABLE_CACHING`,
`CACHE_TTL`, `CACHE_FILE`, `LOGS_DIR`, `ABUSEIPDB_BASE_URL`,
`ENABLE_DISCORD_WEBHOOK`, `URL_DISCORD_WEBHOOK`, `WEBHOOK_USERNAME`,
`CORS_ALLOW_ORIGIN`, `CORS_ALLOW_HEADERS`, `SALT_SHA256`, and
`TRUSTED_PROXIES`.

When adding or changing a constant, update `example.env.php`, [`README.md`](../README.md),
and the relevant documentation page. Never edit or commit local `env.php`.

## Security invariants

- `Authorization` accepts only RFC 6750 Bearer tokens. The endpoint falls back
  to the JSON `token` field for compatibility; the header wins.
- A token is always required. Only the exact `ENVIRONMENT=dev` value skips
  comparison with `API_KEY_CUSTOM`.
- `Security::get_ip()` must not trust proxy headers unless `REMOTE_ADDR` is in
  `TRUSTED_PROXIES` or the built-in Cloudflare CIDRs.
- Preserve `hash_equals()` for production token comparison.
- Do not log or expose official API keys.
- Treat cached and logged IP addresses as personal data.

## Documentation workflow

When PHP behavior changes, update the corresponding English page in
`docs/src/includes/` or `docs/src/api/`, then update [`README.md`](../README.md).
Keep links relative to the document that contains them and verify every link.
Do not create duplicate documentation trees or leave references to deleted
endpoint files.

Documentation must describe the code as it exists, including limitations:
there is no application rate limiter, no automatic upstream retry, cache
rewrites are not locked, webhook exceptions can surface to callers, and
`env.php` is still loaded from the repository root.

## Validation

Run the smallest relevant PHPUnit selection after code changes:

```bash
php tools/phpunit-10.phar
```

Documentation-only changes do not require PHPUnit, but check for stale paths,
Italian text in Markdown, and references to removed `check.php` or `report.php`
files. Do not modify `LICENSE`, runtime cache data, logs, or local secrets.
