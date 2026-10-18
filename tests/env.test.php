<?php
    declare(strict_types=1);

    namespace Zexa\Costants;

    define('API_KEY_ABUSEIPDB', 'test-abuseipdb-key-0000000000000000');
    define('API_KEY_CUSTOM',    'test-custom-key-0000000000000000');
    define('SALT_SHA256',       'test-salt-0000000000000000');

    define('ENVIRONMENT', 'test');

    define('ENABLE_CACHING', true);
    define('CACHE_TTL', 3600);
    define('CACHE_FILE', __DIR__ . '/tmp/cache/ips.json');

    define('LOGS_DIR', __DIR__ . '/tmp/logs/');

    define('ABUSEIPDB_BASE_URL', 'http://127.0.0.1:17490');

    define('ENABLE_DISCORD_WEBHOOK', false);
    define('URL_DISCORD_WEBHOOK', 'http://127.0.0.1:1/disabled');
    define('WEBHOOK_USERNAME', 'test-bot');

    define('CORS_ALLOW_ORIGIN', '*');
    define('CORS_ALLOW_HEADERS', 'Content-Type, Authorization');
    
    define("TRUSTED_PROXIES", []);