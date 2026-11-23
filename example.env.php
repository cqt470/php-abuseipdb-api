<?php

namespace Zexa\Costants;

/*
|--------------------------------------------------------------------------
| AbuseIPDB proxy configuration
|--------------------------------------------------------------------------
| Copy this file to "env.php" and replace every placeholder before deploying.
| This file contains PHP constants; it is not a dotenv configuration file.
|
| Never commit env.php or place it inside the public document root.
*/

/*
|--------------------------------------------------------------------------
| Authentication and upstream API
|--------------------------------------------------------------------------
*/

// Official AbuseIPDB API key used for requests to the upstream service.
define("API_KEY_ABUSEIPDB", "YOUR_KEY_HERE");

// Token required by clients of this proxy.
// Generate a strong value with: openssl rand -hex 64
define("API_KEY_CUSTOM", "YOUR_KEY_HERE");

// Use "dev" only for local development: it disables token checks entirely.
// Any other value enables them.
define("ENVIRONMENT", "production");

// AbuseIPDB API v2 base URL.
define("ABUSEIPDB_BASE_URL", "https://api.abuseipdb.com/api/v2");

/*
|--------------------------------------------------------------------------
| Cache and logs
|--------------------------------------------------------------------------
*/

// Cache AbuseIPDB check responses to reduce upstream API usage.
define("ENABLE_CACHING", true);

// Cache lifetime in seconds. The default is 12 hours.
define("CACHE_TTL", 60 * 60 * 12);

// Runtime cache file. Create it with the contents: []
define("CACHE_FILE", __DIR__ . "/cache/ips.json");

// Directory for categorized application logs.
define("LOGS_DIR", __DIR__ . "/logs/");

/*
|--------------------------------------------------------------------------
| Discord notifications
|--------------------------------------------------------------------------
*/

// When false, cache notifications are not sent.
define("ENABLE_DISCORD_WEBHOOK", true);

// Discord incoming webhook URL.
define("URL_DISCORD_WEBHOOK", "YOUR_KEY_HERE");

// Username shown on Discord webhook messages.
define("WEBHOOK_USERNAME", "php-abuseipdb-api's Bot");

/*
|--------------------------------------------------------------------------
| Queue (Discord notifications and reports sent by a cron job)
|--------------------------------------------------------------------------
| When enabled, Discord notifications and reports are queued instead of being
| sent during the request, and src/cron/flush.php sends them in bulk.
| Schedule it with cron every 10 minutes (see docs/DEPLOYMENT.md).
| When disabled, everything is sent immediately, as in 1.1.
*/

define("ENABLE_QUEUE", false);

// Directory of the queue files. Keep it outside the public document root.
define("QUEUE_DIR", __DIR__ . "/queue/");

// Maximum number of Discord messages sent by one flush (each holds up to 10 embeds).
define("DISCORD_MAX_PER_FLUSH", 5);

// Maximum number of reports sent to AbuseIPDB by one flush (the free plan allows 1000 a day).
define("REPORTS_MAX_PER_FLUSH", 50);

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
*/

// Use a specific origin in production. "*" is intended for development only.
define("CORS_ALLOW_ORIGIN", "*");

// Request headers accepted by the CORS policy.
define("CORS_ALLOW_HEADERS", "Content-Type, Authorization");

/*
|--------------------------------------------------------------------------
| Security helpers
|--------------------------------------------------------------------------
*/

// Salt appended by Security::sha256() before hashing.
define("SALT_SHA256", "YOUR_KEY_HERE");

// Optional trusted proxy CIDRs. An empty list uses the built-in Cloudflare
// ranges. Proxy forwarding headers are trusted only from these networks.
define("TRUSTED_PROXIES", []);
