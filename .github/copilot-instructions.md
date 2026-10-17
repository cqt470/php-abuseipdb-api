# Copilot instructions for `php-abuseipdb-api`

> Last reviewed: 2026-10-17 (after cache TTL implementation).
> This file is loaded as system context by GitHub Copilot Chat on every interaction. Keep it concise, decisive, and current. If a decision changes, update this file in the same PR.
>
> **Update policy**: review this file whenever (a) a Tier 0 fix is completed, (b) a roadmap item in §"Active architectural decisions" is completed or cancelled, (c) a new env var is added, (d) Copilot makes the same mistake twice in one session (add a rule). Routine code refactors do NOT require an update.

## Project context

`php-abuseipdb-api` is a self-hosted PHP proxy for the AbuseIPDB API. Status: **alpha**, no stable release. Branch: `master`. PHP namespace: `Zexa\Includes` and `Zexa\Endpoints`. PHP 8.1+ required (developed on 8.4.26). Zero Composer dependencies, zero framework. Cache is file-based (`cache/ips.json`), no DB, no Redis.

When in doubt about behavior, **read the code, not the docs**. The code is the source of truth. If docs and code diverge, the code wins — fix the docs.

## Documentation map

| File | Language | Scope | Update trigger |
|---|---|---|---|
| `README.md` | English | Public-facing overview, install, API, deployment, security | Any user-facing change |
| `CONTRIBUTING.md` | English | PR workflow, code style, contribution priorities | Workflow changes only |
| `LICENSE` | English (legal) | MIT + attribution clause | Never edit without author's explicit request |
| `docs/includes/endpoint.md` | English (currently Italian — needs migration) | `Endpoint` base class reference | Signature change in `src/includes/endpoint.php` |
| `docs/includes/security.md` | English (planned) | `Security` class reference | Signature change in `src/includes/security.php` |
| `docs/includes/cache.md` | English (planned) | `Cache` class reference | Signature change in `src/includes/cache.php` |
| `docs/includes/webhook.md` | English (planned) | `Webhook` class reference | Signature change in `src/includes/webhook.php` |
| `docs/includes/enums.md` | English (planned) | Enum reference | Enum additions |
| `docs/api/check.md` | English (planned) | Check endpoint reference | Change in `src/api/check.php` |
| `docs/api/report.md` | English (planned) | Report endpoint reference | Change in `src/api/report.php` |
| `.github/copilot-instructions.md` | English | This file | Architectural decisions, conventions |

**All markdown files are English only. No exceptions, no mixed-language sections, no Italian in docs.**

**Do not create docs for files that don't exist yet.** When `docs/api/check.md` is requested, also create `docs/api/report.md` for parity.

**Verify links before adding them.** The previous README linked to `docs/src/api/check.md` which does not exist — the real path is `docs/api/check.md` (planned). Always run `find docs -type f -name '*.md'` before linking.

**Migration note**: `docs/includes/endpoint.md` currently exists in Italian. It must be migrated to English when next touched. Do not bulk-rewrite it in isolation — translate it as part of the next endpoint.php change, or in a dedicated `docs: translate endpoint.md to English` commit.

## Language policy

- **All markdown files are English only. No exceptions.** This includes `README.md`, `CONTRIBUTING.md`, all `docs/**/*.md`, all `.github/**/*.md`.
- The existing `docs/includes/endpoint.md` is currently in Italian — it is a known debt, to be migrated to English when next touched (see Documentation map migration note).
- Code identifiers, comments, commit messages, PR titles: **English**.
- Existing Italian comments in code (e.g., `// TODO: rimuovere la entry` in `cache.php`): do not bulk-rewrite. Only translate when touching that line for another reason.
- Error messages returned by `Endpoint::send_error()` are currently hardcoded in Italian. This is a known bug, tracked in roadmap. Do not translate them in isolation — wait for the coordinated migration.
- User-facing strings in `env.php` defaults (e.g., `WEBHOOK_USERNAME`): English.
- This file (`copilot-instructions.md`): English.

## Code conventions

- **PSR-12**, 4-space indent, no tabs.
- Endpoint classes are `final`, named `<Verb>Endpoint` (e.g., `CheckEndpoint`, `ReportEndpoint`).
- All endpoints extend `Endpoint` from `src/includes/endpoint.php`.
- Endpoint classes are instantiated at the bottom of their file: `new CheckEndpoint();`.
- Env constants are defined with `define()` in `env.php`. Never use `getenv()`, never read `$_ENV` directly.
- HTTP calls use `file_get_contents` + `stream_context_create`. **Never cURL, never Guzzle.** This is a hard constraint — the project's zero-dependency promise depends on it.
- JSON responses always go through `Endpoint::send_json()` (via `send_success()` / `send_error()`). Never `echo json_encode(...)` directly from an endpoint.
- Type declarations are mandatory: parameters, return types, properties. Use `mixed` only when unavoidable.
- `never` return type for methods that always exit (`send_success`, `send_error`).
- File paths in `include_once` use `__DIR__` relative paths, never absolute (until env.php path migration lands).
- No `echo`, `print`, `var_dump`, `die()` in committed code. Use the framework's response methods.

## Completed decisions (recent — context for Copilot)

These were roadmap items that have now landed in code. Listed here so Copilot knows the current state and does not re-suggest them.

### Tier 0 fix #1: `Security::get_ip()` inverted `!` — DONE

Commit `c75b106`. Line 27 of `src/includes/security.php` was `if(!Security::validate_ip($ip)) return $ip;` (inverted). Now correctly reads `if(Security::validate_ip($ip)) return $ip;` for both the Cloudflare header branch (line 21) and the `REMOTE_ADDR` branch (line 27).

Do not reintroduce the `!`. If touching `Security::get_ip()`, verify the validate check is in the affirmative direction.

### Tier 0 fix #2: `hash_equals()` for token comparison — DONE

Commit `d96f7b4`. `check.php` line 29 and `report.php` line 44 now use:

```php
if(\ENVIRONMENT != "dev" && !hash_equals(\API_KEY_CUSTOM, $this->token)) $this->send_error("Unauthenticated.", 403);
```

Two things to note:

1. The comparison is now timing-safe via `hash_equals(\API_KEY_CUSTOM, $this->token)` — argument order matters: secret first, user input second. Do not invert.
2. **Auth is fully skipped when `ENVIRONMENT === "dev"`** (see new env var below). This is intentional for local development but a footgun in production. Flag in any PR that touches auth if `ENVIRONMENT` is not enforced to `"production"` in prod deployment paths.

### Cache TTL enforcement — DONE

Commit `07276d9`. `CACHE_TTL` (defined in `env.php`, default 43200s = 12h) is now enforced:

- `Cache::check_for_entry()` detects entries whose `added_at` is older than `time() - CACHE_TTL`.
- On expired entry, calls `Cache::delete_entry()` to remove it, sends a Discord webhook (color `#444444`, title "TTL Reached"), and returns `false`.
- New method `Cache::delete_entry(string $key, string $value): void` — rewrites `cache/ips.json` without the matching entry.

**Known caveats** (do not fix without explicit request, but be aware):

1. `check_for_entry()` calls `delete_entry()` inside its own loop, which re-reads and rewrites the JSON file. Inefficient but functionally correct. Multiple file reads per request.
2. The "TTL Reached" webhook payload does not include a `timestamp` field (other webhooks do). Minor inconsistency.
3. `delete_entry()` is also called outside `check_for_entry()` nowhere yet; it is part of the public API of `Cache` and may be used by future GDPR deletion endpoints.

When touching `Cache`, do not regress to non-eviction behavior. The TODO comment at the old line 34 (`// TODO: rimuovere la entry e ritornare false`) has been replaced with implementation — remove stale TODO references in docs if found.

## Active architectural decisions

These are decisions in flight. When touching related code, take them into account. Do not implement them silently — they are listed here so you know what is **planned**, not what is done. Items that have landed move to §"Completed decisions" above.

### Tier 0 fix #3: `HTTP_CF_CONNECTING_IP` spoofing — STILL OPEN

Currently `Security::get_ip()` trusts `HTTP_CF_CONNECTING_IP` unconditionally if present. Should verify that `REMOTE_ADDR` is in Cloudflare's IP ranges (https://www.cloudflare.com/ips/) before trusting the header. Status: **not started**. This is the only remaining Tier 0 item.

When implementing, also update §"Completed decisions" above by removing this item from Active and adding it to Completed.

### Bearer token auth migration

Current: `token` field in JSON body, compared with `API_KEY_CUSTOM` via `hash_equals()` (skipped if `ENVIRONMENT === "dev"`).
Target: `Authorization: Bearer <token>` HTTP header.
Status: **not started**. Roadmap item.

When implementing:
- Keep backward compatibility for one release: accept both body `token` and `Authorization` header.
- Update `README.md` §API endpoints examples (curl, JS, PHP).
- Update `docs/api/check.md` and `docs/api/report.md`.
- Add a deprecation log entry when body `token` is used.
- Preserve the `ENVIRONMENT === "dev"` skip behavior — do not tighten dev mode without explicit request.

### CORS centralization

Current: `header("Access-Control-Allow-Origin: *")` hardcoded at top of `check.php` and `report.php`.
Target: `define("CORS_ALLOW_ORIGIN", "https://example.com")` in `env.php`, set once in the front controller.
Status: **not started**. Blocked by router migration (next).

When implementing, remove the hardcoded `header()` calls from individual endpoint files.

### env.php path migration

Current: `env.php` lives in repo root, loaded via `include_once __DIR__."/../../env.php"` from `src/includes/`.
Target: `env.php` lives outside webroot (e.g., `/etc/php-abuseipdb/env.php`), path is configurable via `ENV_FILE_PATH` env var or hardcoded absolute.
Status: **not started**.

When implementing:
- Update `security.php` and `webhook.php` include paths.
- Update `.gitignore` (still ignore `env.php` wherever it is).
- Update README §Production deployment.

### Router front controller

Current: each endpoint is a standalone `.php` file in `src/api/`, accessible directly via `/src/api/check.php`.
Target: single `src/index.php` front controller with a route table. Endpoint files become private (only included by index.php).
Status: **not started**. TODO markers exist in `check.php` (line 9) and `report.php` (line 10).

When implementing:
- Add `src/index.php` with route table.
- Add Nginx `try_files` config in README.
- Remove direct-access TODOs from endpoint files.
- Centralize CORS and (future) bearer auth in `index.php`.

### Honeypot (future, do not implement proactively)

Closed-loop honeypot pattern is in roadmap as a future enhancement. Do not add honeypot code or docs without explicit user request. When requested:
- New endpoint `src/api/honeypot.php` that logs + auto-reports via `Security::report()`.
- Discord webhook on each honeypot hit (use color `#e67e22` to distinguish from cache-add `#2ecc71` and TTL-evict `#444444`).
- Document GDPR implications (IPs logged in honeypot are personal data).
- When the router front controller lands, register honeypot routes in the route table, not as standalone files.

## Common workflows

### "Aggiorna i .md" / "Update the docs"

1. Run `find . -type f -name '*.md'` to list all docs.
2. Read every `.md` and every `.php` in `src/`.
3. For each `.md`, check:
   - Function signatures, parameter types, return types match code.
   - Code examples use the current API surface (parameter names, types, response shape).
   - File paths in links exist (`[text](./path)` → verify with `ls path`).
4. Update `README.md` last, after internal docs are consistent.
5. Do not create new `.md` files unless explicitly requested.
6. Commit message: `docs: sync <files> with code state`.

### Adding a new endpoint

1. Create `src/api/<name>.php` with `final class <Name>Endpoint extends Endpoint`.
2. Instantiate at the bottom: `new <Name>Endpoint();`.
3. Add to `docs/api/<name>.md` (Italian).
4. Add to endpoint table in `README.md` (English) with: Method, Path, Action, Description.
5. Add required parameters, curl/JS/PHP examples, response shape to README.
6. Add any new env vars to `example.env.php` and the env table in README.
7. Add new error messages to the error table in README (preserve Italian wording if the code is Italian).
8. When router lands, add route to `src/index.php`.

### Changing an env var

1. Update `example.env.php`.
2. Update env table in `README.md` §Configuration.
3. Update any code that reads it.
4. If the var changes behavior (e.g., `ENABLE_CACHING` from true to false), update README §Quick start and §Known limitations.

### Touching `Security::get_ip()` or `Security::validate_ip()`

1. Update `docs/includes/security.md`.
2. Update README §Security if behavior changed.
3. If Cloudflare header handling changed, update README §Production deployment (IP allowlist note).
4. Verify the inverted-`!` bug is not reintroduced (Tier 0 fix #1 — see Completed decisions).
5. If you add Cloudflare IP range validation (Tier 0 fix #3, still open), move that item from "Active" to "Completed" in this file.

### Touching `Cache` (any method)

1. Update `docs/includes/cache.md`.
2. If signature of `check_for_entry`, `add_entry`, `get_entry`, or `delete_entry` changes, update README §Configuration (env vars) and any references to cache behavior.
3. If eviction behavior changes, update README §Known limitations (remove the "Cache has no TTL" line if it is still there).
4. Preserve the webhook call for TTL eviction unless explicitly asked otherwise — it is intentional, users want visibility on cache churn.
5. If you change `delete_entry()` to be used by other code paths (e.g., GDPR deletion endpoint), document the new caller.

### Touching `Endpoint::send_json()` or response shape

1. Update `docs/includes/endpoint.md` (response shape section).
2. Update README §API endpoints → Response shape.
3. Update all per-endpoint examples in README.
4. Update error table in README if error codes changed.

## Files to never touch without explicit request

- `LICENSE` — legal text, only author edits.
- `.gitignore` — only when adding new ignore patterns for new file types.
- `CONTRIBUTING.md` — only when workflow changes.
- `env.php` (when it exists locally) — secrets, never commit.
- `cache/ips.json` — runtime data, never commit.
- This file (`.github/copilot-instructions.md`) — only when architectural decisions change. Not for routine doc updates.

## Verification checklist before any docs PR

- [ ] All `[text](./path)` links resolve to existing files.
- [ ] Function signatures in docs match code (`grep "function <name>" src/`).
- [ ] Parameter types in docs match `get_parameter()` / `get_parameters()` calls.
- [ ] Code examples in docs use the current response shape (run them mentally).
- [ ] **No Italian in any markdown file.** If `docs/includes/endpoint.md` is touched, translate it to English in the same PR.
- [ ] No "TODO" left in README body text (only as `- [ ]` items in Roadmap).
- [ ] No new `.md` file created without explicit request.
- [ ] README badge `alpha` is present.
- [ ] Known limitations section reflects current code (verify each item against code; specifically: "Cache has no TTL" must be removed, "Token comparison uses `==`" must be removed).
- [ ] If `env.php` constants changed, env table in README is updated. Current env vars are: `API_KEY_ABUSEIPDB`, `API_KEY_CUSTOM`, `ENVIRONMENT`, `ENABLE_CACHING`, `CACHE_TTL`, `ENABLE_DISCORD_WEBHOOK`, `URL_DISCORD_WEBHOOK`, `WEBHOOK_USERNAME`.
- [ ] If error messages changed, error table in README is updated.
- [ ] No links to `docs/src/api/*.md` (wrong path, common mistake).
- [ ] If cache behavior changed, verify the webhook list (currently: "New IP added to cache" green, "TTL Reached" gray) is consistent in docs.

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
- Whether to translate the existing `docs/includes/endpoint.md` from Italian to English as part of an unrelated change (default: yes if you're already touching that file, no if you're not — but flag the debt).
- Whether to implement a roadmap item or just document it (default: document only).
- Whether honeypot code is in scope (default: never without explicit request).
- Whether to add tests (default: no, until PHPUnit suite is set up — roadmap item).
- Whether to optimize `Cache::check_for_entry()` (currently does multiple file reads via `delete_entry()` call inside loop). Default: leave as-is unless explicitly asked.
- Whether to add a `timestamp` field to the "TTL Reached" webhook payload (currently missing, other webhooks have it). Default: leave as-is unless explicitly asked.
- Whether to enforce `ENVIRONMENT !== "dev"` in production deployment paths (e.g., via a startup check). Default: leave as-is, document as a footgun in README §Production.

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
