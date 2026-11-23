# how to install a Cloudflare Worker that connects to php-abuseipdb-api
Last version officially supported: v1.2.1-stable (newer versions may work)

The Worker sits in front of a website (e.g. Vaultwarden). It answers `403` to
requests for sensitive paths (`.env`, `.git`, `wp-admin`, backups, ...) and
reports the IP to AbuseIPDB as "Bad Web Bot" through php-abuseipdb-api, so the
AbuseIPDB key stays on your server and the Worker only knows the proxy token.
Optionally, it also blocks visitors whose AbuseIPDB score is too high.

## Requirements
- A web server with php-abuseipdb-api
- A subdomain connected to Cloudflare
- Recommended: `ENABLE_QUEUE` on and the cron flush scheduled (see
  [DEPLOYMENT.md](./DEPLOYMENT.md#queue-and-cron-optional)). A scanner hits
  many paths in a few seconds: with the queue the reports on the same IP are
  merged and sent once, without it AbuseIPDB refuses every report after the
  first one for 15 minutes (`429`, only logged by the Worker).

## Steps (Web dashboard)
1. Visit [The Cloudflare Dashboard](https://dash.cloudflare.com/)
2. On the side panel, click on Compute > Workers & Pages
3. Click on Create application
4. Choose Start with Hello World! and click Deploy
5. On the top bar click on Domains and then on the blue Add domain button, add your subdomain (e.g.: api.yoursite.com). Choose Enable for: Production
6. On the top right side of the page click on Edit code and replace everything with:
```js
// Cloudflare Worker in front of a website: blocks requests to sensitive paths and reports
// the IP to AbuseIPDB through php-abuseipdb-api, so the AbuseIPDB key never leaves your server.
//
// Variables (Settings > Variables and Secrets):
//   ABUSEIPDB_PROXY_URL    text    e.g. https://api.yoursite.com/api/abuseipdb.php
//   ABUSEIPDB_PROXY_TOKEN  secret  the API_KEY_CUSTOM of your env.php
//   BLOCK_MIN_SCORE        text    optional, 1-100: also blocks visitors whose AbuseIPDB score is at least this

// Files and folders that must never be served: the whole path segment must match
const BLOCKED_NAMES = /(?:^|\/)(?:\.env(?:\.[^/]*)?|\.git|\.svn|\.hg|\.aws|\.ssh|\.htaccess|\.htpasswd|\.idea|\.vscode|\.ds_store|wp-config\.php|wp-admin|wp-includes|wp-content|composer\.(?:json|lock)|package(?:-lock)?\.json|yarn\.lock|auth\.json|ftpsync\.settings|sftp-config\.json)(?:\/|$)/i;
// Extensions of backups, dumps and configs: any file ending with them (e.g. /backup.sql)
const BLOCKED_EXTENSIONS = /\.(?:bak|old|swp|log|sql|ya?ml)$/i;

// Uptime monitors are never reported
const MONITORING_BOTS = /HetrixTools|UptimeRobot|Pingdom|StatusCake|Better Uptime/i;

const CATEGORY_BAD_WEB_BOT = 21;
const MAX_COMMENT = 1024;         // AbuseIPDB limit
const CHECK_CACHE_SECONDS = 3600; // how long a score decision is kept by the Worker

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const ip = request.headers.get("CF-Connecting-IP") || "";
    const userAgent = request.headers.get("User-Agent") || "Unknown";
    const isMonitor = MONITORING_BOTS.test(userAgent);

    if (BLOCKED_NAMES.test(url.pathname) || BLOCKED_EXTENSIONS.test(url.pathname)) {
      if (ip && !isMonitor) {
        // in the background: the 403 is not delayed by the report
        ctx.waitUntil(report(env, ip, `Cloudflare Worker blocked a request to ${url.pathname}. UA: ${userAgent}`));
      }
      return deny();
    }

    if (ip && !isMonitor && await hasBadScore(env, ctx, request, ip)) {
      return deny();
    }

    // everything else goes to the origin unchanged
    return fetch(request);
  },
};

function deny() {
  return new Response("Access denied, this action has been logged.", {
    status: 403,
    headers: { "Content-Type": "text/plain" },
  });
}

/**
 * Calls php-abuseipdb-api. Returns the HTTP status and the decoded JSON (null if it is not JSON).
 */
async function callProxy(env, body, timeoutMs) {
  if (!env.ABUSEIPDB_PROXY_URL || !env.ABUSEIPDB_PROXY_TOKEN) {
    throw new Error("ABUSEIPDB_PROXY_URL or ABUSEIPDB_PROXY_TOKEN is not configured in the Worker settings.");
  }

  const response = await fetch(env.ABUSEIPDB_PROXY_URL, {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${env.ABUSEIPDB_PROXY_TOKEN}`,
      "Content-Type": "application/json",
      "Accept": "application/json",
    },
    body: JSON.stringify(body),
    signal: AbortSignal.timeout(timeoutMs),
  });

  let json = null;
  try { json = await response.json(); } catch { /* not JSON, e.g. a Cloudflare error page */ }
  return { status: response.status, json };
}

/**
 * Reports an IP as "Bad Web Bot". With ENABLE_QUEUE on, the proxy answers 202 and the cron
 * flush sends it later, merging the reports on the same IP (AbuseIPDB accepts one every 15 minutes).
 */
async function report(env, ip, comment) {
  try {
    const { status, json } = await callProxy(env, {
      action: "report",
      ip,
      categories: [CATEGORY_BAD_WEB_BOT],
      comment: comment.slice(0, MAX_COMMENT),
    }, 10000);

    if (status === 200 || status === 202) {
      console.log(`Reported ${ip} (${json?.message ?? status}).`);
    } else if (status === 429) {
      console.log(`${ip} not reported: AbuseIPDB accepts the same IP once every 15 minutes.`);
    } else {
      console.error(`Report of ${ip} failed: HTTP ${status}`, json?.message ?? "");
    }
  } catch (error) {
    console.error(`Report of ${ip} failed:`, error.message);
  }
}

/**
 * True when BLOCK_MIN_SCORE is set and the visitor's AbuseIPDB score reaches it.
 * The decision is cached by the Worker for CHECK_CACHE_SECONDS, so a visitor costs one check
 * per hour; if the proxy is down or slow the visitor is let through (fail open).
 */
async function hasBadScore(env, ctx, request, ip) {
  const minScore = Number(env.BLOCK_MIN_SCORE);
  if (!(minScore >= 1 && minScore <= 100)) return false;

  const cache = caches.default;
  const key = new Request(new URL(`/__abuseipdb-check/${encodeURIComponent(ip)}`, request.url).toString());

  const cached = await cache.match(key);
  if (cached) return Number(await cached.text()) >= minScore;

  try {
    const { status, json } = await callProxy(env, { action: "check", ip }, 3000);
    if (status !== 200) {
      console.error(`Check of ${ip} failed: HTTP ${status}`, json?.message ?? "");
      return false;
    }

    const score = Number(json?.parameters?.info?.data?.abuseConfidenceScore ?? 0);
    ctx.waitUntil(cache.put(key, new Response(String(score), {
      headers: { "Cache-Control": `max-age=${CHECK_CACHE_SECONDS}` },
    })));
    return score >= minScore;
  } catch (error) {
    console.error(`Check of ${ip} failed:`, error.message);
    return false;
  }
}
```
7. Click on Deploy, then go back to the Worker and open Settings > Variables and Secrets. Add:

   | Type | Name | Value |
   | --- | --- | --- |
   | Text | `ABUSEIPDB_PROXY_URL` | Full URL of the endpoint, e.g. `https://api.yoursite.com/api/abuseipdb.php` |
   | Secret | `ABUSEIPDB_PROXY_TOKEN` | The `API_KEY_CUSTOM` of your `env.php` |
   | Text | `BLOCK_MIN_SCORE` | Optional, see [Blocking by score](#blocking-by-score) |

   If the Worker had an `ABUSEIPDB_API_KEY` secret from an older script, delete it: the AbuseIPDB key now lives only in `env.php`.
8. Click on Deploy to apply the variables.

## Test
```bash
# A sensitive path must answer 403 "Access denied, this action has been logged."
curl -i https://vault.yoursite.com/.env
```

- The Worker logs (Workers & Pages > your Worker > Logs) show
  `Reported <ip> (queued).`, or `(ok)` without the queue.
- With the queue, `queue/reports.json` on the server contains the IP until the
  next cron flush, which then posts the "Reports: 1 sent" summary on Discord.
- Every other path must reach the origin as before.

If the Worker logs `HTTP 403` or `HTTP 401`, the token in
`ABUSEIPDB_PROXY_TOKEN` does not match `API_KEY_CUSTOM`.

## Blocking by score
With `BLOCK_MIN_SCORE` set (1-100, e.g. `75`), every visitor is checked with
the proxy's `check` action and gets `403` when its `abuseConfidenceScore` is at
least that value. Keep in mind:

- each visitor costs one AbuseIPDB check per hour (the Worker caches the score
  for an hour, the proxy for `CACHE_TTL`); the free plan allows 1000 checks a
  day;
- if the proxy is down or answers in more than 3 seconds, the visitor is let
  through;
- shared and mobile IPs sometimes have a score: on a private service such as
  Vaultwarden you could block yourself. Start with a high value, or leave the
  variable unset to only block and report the sensitive paths.
