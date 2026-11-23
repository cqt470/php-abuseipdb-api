<?php
    /**
     * Serves src/api/abuseipdb.php through the PHP built-in server with a
     * configuration taken from FINDINGS_* environment variables.
     */
    namespace Zexa\Costants;

    $tmp = getenv('FINDINGS_TMP');

    define('API_KEY_ABUSEIPDB', 'test-abuseipdb-key-0000000000000000');
    define('API_KEY_CUSTOM',    getenv('FINDINGS_CUSTOM_KEY') ?: 'test-custom-key-0000000000000000');
    define('SALT_SHA256',       'test-salt-0000000000000000');
    define('ENVIRONMENT',       getenv('FINDINGS_ENV') ?: 'test');
    define('ABUSEIPDB_BASE_URL', getenv('FINDINGS_BASE_URL'));

    define('ENABLE_CACHING', true);
    define('CACHE_TTL', 3600);
    define('CACHE_FILE', $tmp.'/ips.json');
    define('LOGS_DIR',   $tmp.'/logs/');

    define('ENABLE_DISCORD_WEBHOOK', getenv('FINDINGS_WEBHOOK') === '1');
    define('URL_DISCORD_WEBHOOK', 'http://127.0.0.1:1/unreachable');
    define('WEBHOOK_USERNAME', 'test-bot');

    define('CORS_ALLOW_ORIGIN', '*');
    define('CORS_ALLOW_HEADERS', 'Content-Type, Authorization');
    define('TRUSTED_PROXIES', []);

    require __DIR__.'/../../../src/api/abuseipdb.php';
