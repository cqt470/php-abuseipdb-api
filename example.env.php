<?php
    namespace Zexa\Costants;

    define("API_KEY_ABUSEIPDB", "YOUR_KEY_HERE"); // used to communicate with AbuseIPDB
    define("API_KEY_CUSTOM", "YOUR_KEY_HERE"); // used to protect your endpoints
    define("ENVIRONMENT", "dev"); // when "dev" skips API key verification for the exposed endpoints.
    define("ENABLE_CACHING", true); // heavily suggested to leave it on true. Setting it to false will use your AbuseIPDB tokens at every request!!!
    define("CACHE_TTL", 60*60*12); // 12 hours
    define("ENABLE_DISCORD_WEBHOOK", true);
    define("URL_DISCORD_WEBHOOK", "YOUR_KEY_HERE");
    define("WEBHOOK_USERNAME", "php-abuseipdb-api's Bot");