# Copilot instructions for `php-abuseipdb-api`

> Last reviewed: 2026-10-17 (after Logger + enums + single endpoint + cache constructor change).
> This file is loaded as system context by GitHub Copilot Chat on every interaction. Keep it concise, decisive, and current. If a decision changes, update this file in the same PR.
>
> **Update policy**: review this file whenever (a) a Tier 0 fix is completed, (b) a roadmap item in §"Active architectural decisions" is completed or cancelled, (c) a new env var is added, (d) a new file or class is added under `src/`, (e) Copilot makes the same mistake twice in one session (add a rule). Routine code refactors do NOT require an update.

## Project context

`php-abuseipdb-api` is a self-hosted PHP proxy for the AbuseIPDB API. Status: **alpha**, no stable release. Branch: `master`. PHP namespace: `Zexa\Includes` and `Zexa\Endpoints`. PHP 8.1+ required (developed on 8.4.26). Zero Composer dependencies, zero framework. Cache is file-based (`cache/ips.json`), no DB, no Redis. Logs are file-based (`logs/*.log`), no DB.

When in doubt about behavior, **read the code, not the docs**. The code is the source of truth. If docs and code diverge, the code wins — fix the docs.

## File structure (current)

```
php-abuseipdb-api/
├── .github/copilot-instructions.md     # This file
├── .gitignore                          # Ignores env.php, cache/ips.json, *-old*, .aider*, logs/*
├── CONTRIBUTING.md
├── LICENSE                             # MIT + attribution to Gioele Cairo (cqt470)
├── README.md
├── docs/
│   ├── includes/
│   │   └── endpoint.md                 # Italian, in sync with code
│   └── src/                            # LEGACY, partially stale, duplicate of docs/includes/
│       ├── api/
│       │   ├── check.md                # References old check.php (deleted)
│       │   └── report.md               # References old report.php (deleted)
│       └── includes/
│           ├── cache.md
│           ├── endpoint.md             # Differs from docs/includes/endpoint.md
│           ├── enums.md
│           ├── index.md
│           ├── security.md
│           └── webhook.md
├── example.env.php                     # All env vars
├── src/
│   ├── api/
│   │   └── abuseipdb.php               # Single endpoint, both check + report actions
│   └── includes/
│       ├── cache.php                   # Cache class with TTL enforcement + delete_entry
│       ├── endpoint.php                # Endpoint base, bearer auth, CORS, Logger property
│       ├── enums.php                   # log_priority enum (debug, info)
│       ├── index.php                   # Loads all includes
│       ├── logger.php                  # Logger class (file-based)
│       ├── security.php                # Security class (get_ip, get_ip_info, validate_ip)
│       └── webhook.php                 # Webhook class (Discord)
├── test/
│   ├── check.html                      # Manual test page, updated for /api/abuseipdb.php + Bearer
│   └── report.html
├── cache/
│   └── ips.json                        # Runtime cache (gitignored, must exist!)
└── logs/                               # Runtime logs (gitignored)
    ├── auth.log
    └── ...
```

## Documentation map

| File | Language | Scope | Update trigger |
|---|---|---|---|
| `README.md` | English | Public-facing overview, install, API, deployment, security | Any user-facing change |
| `CONTRIBUTING.md` | English | PR workflow, code style, contribution priorities | Workflow changes only |
| `LICENSE` | English (legal) | MIT + attribution clause | Never edit without author's explicit request |
| `docs/includes/endpoint.md` | Italian (known debt) | `Endpoint` base class reference | Signature change in `src/includes/endpoint.php` |
| `docs/src/api/check.md` | Italian, STALE | References deleted `check.php` | Either delete or rewrite as `docs/api/abuseipdb.md` |
| `docs/src/api/report.md` | Italian, STALE | References deleted `report.php` | Either delete or rewrite as `docs/api/abuseipdb.md` |
| `docs/src/includes/*.md` | Italian, partially stale | Class references | Verify against code before linking |
| `.github/copilot-instructions.md` | English | This file | Architectural decisions, conventions |

**All markdown files are English only. No exceptions, no mixed-language sections, no Italian in docs.**

The existing `docs/includes/endpoint.md` and `docs/src/**/*.md` are in Italian — they are known debt. Migrate when next touched. Do not bulk-rewrite in isolation.

**Documentation consolidation is planned** — `docs/src/` and `docs/includes/` overlap. Pick one structure (proposal: `docs/api/` for endpoint docs, `docs/includes/` for class docs) and migrate.

## Language policy

- **All markdown files are English only. No exceptions.** This includes `README.md`, `CONTRIBUTING.md`, all `docs/**/*.md`, all `.github/**/*.md`.
- Existing Italian docs are known debt. Migrate to English when next touched.
- Code identifiers, comments, commit messages, PR titles: **English**.
- Existing Italian comments in code (e.g., `// TODO: rimuovere la entry` in `cache.php`): do not bulk-rewrite. Only translate when touching that line for another reason.
- Error messages returned by `Endpoint::send_error()` are currently hardcoded in Italian. This is a known bug, tracked in roadmap. Do not translate them in isolation — wait for the coordinated migration.
- User-facing strings in `env.php` defaults (e.g., `WEBHOOK_USERNAME`): English.
- This file (`copilot-instructions.md`): English.

## Code conventions

- **PSR-12**, 4-space indent, no tabs.
- Endpoint classes are `final`, named `<Verb>Endpoint` (e.g., `AbuseIPDBEndpoint`).
- All endpoints extend `Endpoint` from `src/includes/endpoint.php`.
- Endpoint classes are instantiated at the bottom of their file: `new AbuseIPDBEndpoint();`.
- Env constants are defined with `define()` in `env.php`. Never use `getenv()`, never read `$_ENV` directly.
- HTTP calls use `file_get_contents` + `stream_context_create`. **Never cURL, never Guzzle.** This is a hard constraint — the project's zero-dependency promise depends on it.
- JSON responses always go through `Endpoint::send_json()` (via `send_success()` / `send_error()`). Never `echo json_encode(...)` directly from an endpoint.
- Type declarations are mandatory: parameters, return types, properties. Use `mixed` only when unavoidable.
- `never` return type for methods that always exit (`send_success`, `send_error`).
- File paths in `include_once` use `__DIR__` relative paths, never absolute (until env.php path migration lands).
- No `echo`, `print`, `var_dump`, `die()` in committed code. Use the framework's response methods.
- Enum names use `snake_case` (e.g., `log_priority`). Enum cases use `lowercase` (e.g., `debug`, `info`).

## Environment variables (current `example.env.php`)

```php
API_KEY_ABUSEIPDB         // Official AbuseIPDB key, sent as Key: header to api.abuseipdb.com
API_KEY_CUSTOM            // Bearer token clients send in Authorization header
ENVIRONMENT               // "dev" (skips auth) | "production" (enforces auth). Default "dev".
ENABLE_CACHING            // bool, default true. When false, hits AbuseIPDB on every request.
CACHE_TTL                 // int seconds, default 43200 (12h). TTL for cache entries.
ENABLE_DISCORD_WEBHOOK    // bool, default true.
URL_DISCORD_WEBHOOK       // Discord webhook URL.
WEBHOOK_USERNAME          // Display name for webhook messages.
CORS_ALLOW_ORIGIN         // string, default "*". Set to specific origin in production.
CORS_ALLOW_HEADERS        // string, default "Content-Type, Authorization".
```

## Completed decisions (recent — context for Copilot)

These were roadmap items that have now landed in code. Listed here so Copilot knows the current state and does not re-suggest them.

### Tier 0 fix #1: `Security::get_ip()` inverted `!` — DONE

Commit `c75b106`. Line 27 of `src/includes/security.php` was `if(!Security::validate_ip($ip)) return $ip;` (inverted). Now correctly reads `if(Security::validate_ip($ip)) return $ip;` for both the Cloudflare header branch (line 21) and the `REMOTE_ADDR` branch (line 27).

Do not reintroduce the `!`. If touching `Security::get_ip()`, verify the validate check is in the affirmative direction.

**Caveat**: the signature is now `?string` but the implementation always returns a string (it falls back to `"127.0.0.1"`). The nullable signature is misleading and should be tightened to `string`. Track as a minor cleanup.

### Tier 0 fix #2: `hash_equals()` for token comparison — DONE

Commit `d96f7b4`. `abuseipdb.php` line 20 now uses:

```php
if(\ENVIRONMENT != "dev" && !hash_equals(\API_KEY_CUSTOM, $this->token)){
    $this->logger->log("auth", log_priority::debug, "Authentication to /abuseipdb API failed via token.", true);
    $this->send_error("Unauthorized.", 403);
}
```

Two things to note:

1. The comparison is now timing-safe via `hash_equals(\API_KEY_CUSTOM, $this->token)` — argument order matters: secret first, user input second. Do not invert.
2. **Auth is fully skipped when `ENVIRONMENT === "dev"`**. This is intentional for local development but a footgun in production. Flag in any PR that touches auth if `ENVIRONMENT` is not enforced to `"production"` in prod deployment paths.

### Tier 0 fix #3: `HTTP_CF_CONNECTING_IP` spoofing — STILL OPEN

Currently `Security::get_ip()` trusts `HTTP_CF_CONNECTING_IP` unconditionally if present. Should verify that `REMOTE_ADDR` is in Cloudflare's IP ranges (https://www.cloudflare.com/ips/) before trusting the header. Status: **not started**. This is the only remaining Tier 0 item.

When implementing, also update §"Completed decisions" above by removing this item from Active and adding it to Completed.

### Cache TTL enforcement — DONE

Commit `07276d9`. `CACHE_TTL` (defined in `env.php`, default 43200s = 12h) is now enforced:

- `Cache::check_for_entry()` detects entries whose `added_at` is older than `time() - CACHE_TTL`.
- On expired entry, calls `Cache::delete_entry()` to remove it, sends a Discord webhook (color `#444444`, title "TTL Reached"), and returns `false`.
- New method `Cache::delete_entry(string $key, string $value): void` — rewrites `cache/ips.json` without the matching entry.

**Known caveats** (do not fix without explicit request, but be aware):

1. `check_for_entry()` calls `delete_entry()` inside its own loop, which re-reads and rewrites the JSON file. Inefficient but functionally correct. Multiple file reads per request.
2. The "TTL Reached" webhook payload does not include a `timestamp` field (other webhooks do). Minor inconsistency.
3. `Cache::__construct()` now **throws an `Exception` if `cache/ips.json` does not exist** (changed from previous behavior that created the file). The installation step `touch cache/ips.json` is mandatory. Document this in README.

### Bearer token auth migration — DONE

Commits `c019018`, `2d3baaa`. `Endpoint::get_token()` in `src/includes/endpoint.php` reads `Authorization: Bearer <token>` HTTP header via regex `/^Bearer\s+(\S+)\s*$/i`.

`AbuseIPDBEndpoint::__construct()` calls `$this->get_token() ?? $this->get_parameter("token", "string", true) ?? null` — Bearer header wins, body `token` is legacy fallback. When the header is missing and the body has no `token`, returns `401 Unauthenticated.`. When the token does not match, returns `403 Unauthorized.` and logs an `auth` event at `debug` priority via `$this->logger->log()`.

**Caveat**: `get_token()` does not defensively handle `getallheaders()` returning `false` on some PHP-FPM setups. The access to `["Authorization"]` may produce a PHP 8+ deprecation warning. A defensive rewrite is planned:

```php
protected function get_token(): ?string {
    $headers = getallheaders();
    if ($headers === false) return null;
    $bearer_token = $headers["Authorization"] ?? "";
    if (!preg_match('/^Bearer\s+(\S+)\s*$/i', $bearer_token, $matches)) return null;
    return $matches[1];
}
```

### Single endpoint unification — DONE

Commits `ab480c3`, `2d3baaa`. `src/api/check.php` and `src/api/report.php` were merged into `src/api/abuseipdb.php`. Class `AbuseIPDBEndpoint` (final, extends `Endpoint`) handles both `check` and `report` actions via `register_action()` + `route_action()`. Old files deleted.

The path public URL is now `/api/abuseipdb.php` (still directly accessible). When the router front controller lands, this will become `/api/abuseipdb` via rewrite.

### CORS centralisation — DONE

Commits `b0c5ce6`, `46c67ce`. `Endpoint::handle_cors()` is called from `Endpoint::__construct()` BEFORE the POST method check. This ordering is critical — `OPTIONS` preflight requests must receive CORS headers, and the POST check would otherwise reject `OPTIONS` with HTTP `405`.

Headers set:
- `Access-Control-Allow-Origin: <CORS_ALLOW_ORIGIN>`
- `Access-Control-Allow-Methods: POST, GET, OPTIONS`
- `Access-Control-Allow-Headers: <CORS_ALLOW_HEADERS>`

`OPTIONS` requests get `204 No Content` + `exit` after headers are sent.

### Logger implementation — DONE (partial)

Commits `810387c`, `40a3390`, `76a1776`, `526ce34`. `Logger` class in `src/includes/logger.php`:

- Constructor takes a folder path, validates it with `realpath()` + `is_dir()`, throws `Exception` if invalid.
- `log(string $category, log_priority $priority, string $text, bool $include_ip = false)` writes to `logs/{category}.log` with format `[PRIORITY] timestamp - text (IP x.x.x.x)`.
- Uses `file_put_contents($filepath, "$row\n", FILE_APPEND)`. **Missing `LOCK_EX`** for atomic appends under concurrency.
- The `Logger` is exposed as `protected Logger $logger` on `Endpoint`, initialised in the constructor with `__DIR__."/../../logs/"`.
- Used in `AbuseIPDBEndpoint::__construct()` line 21 to log auth failures at `debug` priority.

### `log_priority` enum — DONE (partial)

Commit `3409fc1`. `enum log_priority: string` in `src/includes/enums.php` has two cases: `debug`, `info`. Missing: `warning`, `error`, `audit`. Add them when next touching logging code.

### Logger dead code — KNOWN BUG

`src/includes/logger.php` line 18: `if($include_ip) $ip = "Security::get_ip();";` — assigns a string literal to `$ip` which is never used. The actual IP lookup happens at line 24 with `Security::get_ip()` directly. The dead assignment should be removed when next touching the file.

## Active architectural decisions

These are decisions in flight. When touching related code, take them into account. Do not implement them silently — they are listed here so you know what is **planned**, not what is done. Items that have landed move to §"Completed decisions" above.

### env.php path migration

Current: `env.php` lives in repo root, loaded via `include_once __DIR__."/../../env.php"` from `src/includes/`. This places `env.php` inside the webroot if `src/` is served directly.
Target: `env.php` lives outside webroot (e.g., `/etc/php-abuseipdb/env.php`), path is configurable via `ENV_FILE_PATH` env var or hardcoded absolute.
Status: **not started**.

When implementing:
- Update `src/includes/security.php` and `src/includes/webhook.php` include paths.
- Update `.gitignore` (still ignore `env.php` wherever it is).
- Update README §Production deployment.

### Router front controller

Current: each endpoint is a standalone `.php` file in `src/api/`, accessible directly via `/api/abuseipdb.php`.
Target: single `src/index.php` front controller with a route table. Endpoint files become private (only included by index.php).
Status: **not started**.

When implementing:
- Add `src/index.php` with route table.
- Add Nginx `try_files` config in README.
- Centralize CORS and auth in `index.php`.
- Make `src/api/abuseipdb.php` accessible only via the router.

### Honeypot (future, do not implement proactively)

Closed-loop honeypot pattern is in roadmap as a future enhancement. Do not add honeypot code or docs without explicit user request. When requested:
- New endpoint `src/api/honeypot.php` (or router route) that logs + auto-reports via `Security::report()` (currently `Security::get_ip_info` is the only public method, may need a `report()` method added).
- Discord webhook on each honeypot hit (use color `#e67e22` to distinguish from cache-add `#2ecc71` and TTL-evict `#444444`).
- Document GDPR implications (IPs logged in honeypot are personal data).
- When the router front controller lands, register honeypot routes in the route table.

## Common workflows

### "Aggiorna i .md" / "Update the docs"

1. Run `find . -type f -name '*.md'` to list all docs.
2. Read every `.md` and every `.php` in `src/`.
3. For each `.md`, check:
   - Function signatures, parameter types, return types match code.
   - Code examples use the current API surface (parameter names, types, response shape, Bearer auth).
   - File paths in links exist (`[text](./path)` → verify with `ls path`).
4. Update `README.md` last, after internal docs are consistent.
5. Do not create new `.md` files unless explicitly requested.
6. Commit message: `docs: sync <files> with code state`.

### Adding a new endpoint

1. Create `src/api/<name>.php` with `final class <Name>Endpoint extends Endpoint`.
2. Instantiate at the bottom: `new <Name>Endpoint();`.
3. Add to `docs/api/<name>.md` (English).
4. Add to endpoint table in `README.md` with: Method, Path, Action, Description.
5. Add required parameters, curl/JS/PHP examples, response shape to README.
6. Add any new env vars to `example.env.php` and the env table in README.
7. Add new error messages to the error table in README (preserve Italian wording if the code is Italian).
8. When router lands, add route to `src/index.php`.
9. If the endpoint uses the logger, follow the `log_priority` enum (add cases if needed).

### Changing an env var

1. Update `example.env.php`.
2. Update env table in `README.md` §Configuration.
3. Update any code that reads it.
4. If the var changes behavior (e.g., `ENABLE_CACHING` from true to false), update README §Quick start and §Known limitations.

### Touching `Security::get_ip()` or `Security::validate_ip()`

1. Update `docs/src/includes/security.md` (Italian, or migrate to English if consolidation is in progress).
2. Update README §Security if behavior changed.
3. If Cloudflare header handling changed, update README §Production deployment (IP allowlist note).
4. Verify the inverted-`!` bug is not reintroduced (Tier 0 fix #1 — see Completed decisions).
5. If you add Cloudflare IP range validation (Tier 0 fix #3, still open), move that item from "Active" to "Completed" in this file.
6. If you tighten the `?string` return type back to `string`, update this file and any callers.

### Touching `Cache` (any method)

1. Update `docs/src/includes/cache.md` (or its English equivalent after consolidation).
2. If signature of `check_for_entry`, `add_entry`, `get_entry`, or `delete_entry` changes, update README §Configuration (env vars) and any references to cache behavior.
3. If eviction behavior changes, update README §Known limitations.
4. Preserve the webhook call for TTL eviction unless explicitly asked otherwise — it is intentional, users want visibility on cache churn.
5. If you change `delete_entry()` to be used by other code paths (e.g., GDPR deletion endpoint), document the new caller.
6. The constructor throws if `cache/ips.json` does not exist. Document this in README §Installation if not already.

### Touching `Endpoint` (any method)

1. Update `docs/includes/endpoint.md` (Italian) and `docs/src/includes/endpoint.md` (Italian, stale duplicate). Prefer the first as canonical.
2. If you fix the `get_token()` defensive null-check, move the caveat from §"Completed decisions" above to a past note.
3. If signature of `__construct`, `register_action`, `route_action`, `get_token`, `handle_cors`, `send_success`, `send_error`, `add_response_parameter`, `get_parameter`, `get_parameters` changes, update README and docs.

### Touching `Logger` or `log_priority` enum

1. Update `docs/src/includes/logger.md` and `docs/src/includes/enums.md` (or consolidated English equivalents).
2. If you add `LOCK_EX` to `file_put_contents`, remove the caveat from §"Completed decisions → Logger implementation".
3. If you add `warning`, `error`, `audit` cases to `log_priority`, remove the caveat from §"Completed decisions → log_priority enum".
4. If you remove the dead code at line 18, remove the caveat from §"Completed decisions → Logger dead code".

### Touching `Endpoint::send_json()` or response shape

1. Update `docs/includes/endpoint.md` (response shape section).
2. Update README §API endpoints → Response envelope.
3. Update all per-endpoint examples in README.
4. Update error table in README if error codes changed.

## Files to never touch without explicit request

- `LICENSE` — legal text, only author edits.
- `.gitignore` — only when adding new ignore patterns for new file types.
- `CONTRIBUTING.md` — only when workflow changes.
- `env.php` (when it exists locally) — secrets, never commit.
- `cache/ips.json` — runtime data, never commit.
- `logs/*` — runtime data, never commit.
- This file (`.github/copilot-instructions.md`) — only when architectural decisions change. Not for routine doc updates.

## Verification checklist before any docs PR

- [ ] All `[text](./path)` links resolve to existing files.
- [ ] Function signatures in docs match code (`grep "function <name>" src/`).
- [ ] Parameter types in docs match `get_parameter()` / `get_parameters()` calls.
- [ ] Code examples in docs use the current response shape (run them mentally).
- [ ] Code examples use `Authorization: Bearer <token>` header, NOT body `token`.
- [ ] **No Italian in any markdown file.** If `docs/includes/endpoint.md` or `docs/src/**/*.md` is touched, translate it to English in the same PR.
- [ ] No "TODO" left in README body text (only as `- [ ]` items in Roadmap).
- [ ] No new `.md` file created without explicit request.
- [ ] README badge `alpha` is present.
- [ ] Known limitations section reflects current code (verify each item against code):
  - "Cache has no TTL" must be removed (TTL is implemented).
  - "Token comparison uses `==`" must be removed (`hash_equals` is used).
  - "CORS open with `*`" must mention `CORS_ALLOW_ORIGIN` env var.
  - "Two endpoints `/api/check` and `/api/report`" must be replaced with single `/api/abuseipdb.php`.
- [ ] If `env.php` constants changed, env table in README is updated. Current env vars are: `API_KEY_ABUSEIPDB`, `API_KEY_CUSTOM`, `ENVIRONMENT`, `ENABLE_CACHING`, `CACHE_TTL`, `ENABLE_DISCORD_WEBHOOK`, `URL_DISCORD_WEBHOOK`, `WEBHOOK_USERNAME`, `CORS_ALLOW_ORIGIN`, `CORS_ALLOW_HEADERS`.
- [ ] If error messages changed, error table in README is updated.
- [ ] No links to `docs/api/check.md` or `docs/api/report.md` — those files do not exist; the legacy paths are `docs/src/api/check.md` and `docs/src/api/report.md`.
- [ ] If cache behavior changed, verify the webhook list (currently: "New IP added to cache" green `#2ecc71`, "TTL Reached" gray `#444444`) is consistent in docs.
- [ ] If logger behavior changed, verify the log categories and priorities are documented.

## Commit message conventions

- `fix:` bug fixes (e.g., `fix: invert validate_ip check in Security::get_ip`)
- `feat:` new features (e.g., `feat: add bearer token auth`)
- `docs:` documentation only (e.g., `docs: sync endpoint.md with code`)
- `refactor:` code restructuring without behavior change
- `chore:` tooling, configs, CI
- `security:` security-relevant fixes (use this for Tier 0 fixes)
- Scope: imperative mood, lowercase, no period at end
- Reference issue number if applicable: `fix: ... (#42)`

## Things Copilot should ask about, not assume

If a request is ambiguous on any of these, ask before acting:

- Whether to fix the remaining Tier 0 bug (`HTTP_CF_CONNECTING_IP` spoofing, still open) as part of an unrelated change (default: no, flag it separately).
- Whether a change to `env.php` constants should also update `example.env.php`.
- Whether to translate Italian error messages in code (default: no, wait for coordinated migration).
- Whether to translate the existing `docs/includes/endpoint.md` or `docs/src/**/*.md` from Italian to English as part of an unrelated change (default: yes if you're already touching that file, no if you're not — but flag the debt).
- Whether to consolidate `docs/src/` and `docs/includes/` into a single documentation tree (default: no, propose it but do not execute).
- Whether to implement a roadmap item or just document it (default: document only).
- Whether honeypot code is in scope (default: never without explicit request).
- Whether to add tests (default: no, until PHPUnit suite is set up — roadmap item).
- Whether to optimize `Cache::check_for_entry()` (currently does multiple file reads via `delete_entry()` call inside loop). Default: leave as-is unless explicitly asked.
- Whether to add a `timestamp` field to the "TTL Reached" webhook payload (currently missing, other webhooks have it). Default: leave as-is unless explicitly asked.
- Whether to enforce `ENVIRONMENT !== "dev"` in production deployment paths (e.g., via a startup check). Default: leave as-is, document as a footgun in README §Production.
- Whether to fix `get_token()` defensive null-check on `getallheaders()` (default: yes if touching the file, no otherwise).
- Whether to remove the dead code at `Logger::log()` line 18 (default: yes if touching the file, no otherwise).
- Whether to add `warning`, `error`, `audit` cases to `log_priority` (default: yes if the new code needs them, no otherwise).
- Whether to add `LOCK_EX` to `Logger::log()` writes (default: yes if touching the file, no otherwise).
- Whether to tighten `Security::get_ip()` return type from `?string` to `string` (default: yes if touching the file, no otherwise).

## Quick reference: response shape

All API responses follow this envelope (from `Endpoint::send_json()`):

```json
{
  "message": "string or null",
  "success": true,
  "parameters": { ... }
}
```

- `message`: human-readable string or `null`.
- `success`: `true` for HTTP 2xx, `false` otherwise.
- `parameters`: present only when the endpoint adds data (e.g., `info` with AbuseIPDB payload).

Do not deviate from this shape in new endpoints.

## Quick reference: env vars

| Constant | Type | Default |
|---|---|---|
| `API_KEY_ABUSEIPDB` | string | `YOUR_KEY_HERE` |
| `API_KEY_CUSTOM` | string | `YOUR_KEY_HERE` |
| `ENVIRONMENT` | string | `dev` |
| `ENABLE_CACHING` | bool | `true` |
| `CACHE_TTL` | int (seconds) | `43200` |
| `ENABLE_DISCORD_WEBHOOK` | bool | `true` |
| `URL_DISCORD_WEBHOOK` | string | `YOUR_KEY_HERE` |
| `WEBHOOK_USERNAME` | string | `php-abuseipdb-api's Bot` |
| `CORS_ALLOW_ORIGIN` | string | `*` |
| `CORS_ALLOW_HEADERS` | string | `Content-Type, Authorization` |

## Quick reference: webhooks

| Event | Color | Title | Trigger |
|---|---|---|---|
| Cache add | `#2ecc71` (green) | "New IP added to cache" | New IP added to `cache/ips.json`. |
| Cache TTL eviction | `#444444` (gray) | "TTL Reached" | Cached IP expired and deleted. |
| Honeypot (future) | `#e67e22` (orange) | TBD | Honeypot endpoint hit. |
| Auth failure (future) | `#e74c3c` (red) | TBD | Bearer token rejected. |
| Report submitted (future) | `#3498db` (blue) | TBD | Successful report to AbuseIPDB. |
| Upstream down (future) | `#f1c40f` (yellow) | TBD | `file_get_contents` to AbuseIPDB fails. |

Currently only the first two are implemented. The others are planned but not yet wired in.
