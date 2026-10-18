<?php
    namespace Zexa\Costants;

    define("API_KEY_ABUSEIPDB", "YOUR_KEY_HERE"); // used to communicate with AbuseIPDB
    define("API_KEY_CUSTOM", "YOUR_KEY_HERE"); // used to protect your endpoints
    define("ENVIRONMENT", "dev"); // when "dev" skips API key verification for the exposed endpoints.
    define("ENABLE_CACHING", true); // heavily suggested to leave it on true. Setting it to false will use your AbuseIPDB tokens at every request!!!
    define("CACHE_TTL", 60*60*12); // 12 hours
    define("CACHE_FILE", __DIR__."/cache/ips.json");
    define("LOGS_DIR", __DIR__."/logs//");
    define("ABUSEIPDB_BASE_URL", "https://api.abuseipdb.com/api/v2");
    define("ENABLE_DISCORD_WEBHOOK", true);
    define("URL_DISCORD_WEBHOOK", "YOUR_KEY_HERE");
    define("WEBHOOK_USERNAME", "php-abuseipdb-api's Bot");
    define("CORS_ALLOW_ORIGIN", "*"); // remember to configure it depending on your needs, * is highly insecure but is best in development on local environments
    define("CORS_ALLOW_HEADERS", "Content-Type, Authorization"); // Authorization header is needed for compatibility with systems who expect Bearer token based auth
    define("SALT_SHA256", "YOUR_KEY_HERE"); // key used in things such as hashes
    define("TRUSTED_PROXIES", []);