<?php
    /**
     * Runs src/cron/flush.php with a configuration taken from FINDINGS_* environment variables.
     * From the command line: php flush-runner.php. Served by the built-in server it must refuse to run.
     */
    namespace Zexa\Costants;

    $tmp = getenv('FINDINGS_TMP');

    define('API_KEY_ABUSEIPDB', 'test-abuseipdb-key-0000000000000000');
    define('API_KEY_CUSTOM',    'test-custom-key-0000000000000000');
    define('SALT_SHA256',       'test-salt-0000000000000000');
    define('ENVIRONMENT',       'test');
    define('ABUSEIPDB_BASE_URL', getenv('FINDINGS_BASE_URL') ?: 'http://127.0.0.1:1');

    define('ENABLE_CACHING', true);
    define('CACHE_TTL', 3600);
    define('CACHE_FILE', $tmp.'/ips.json');
    define('LOGS_DIR',   $tmp.'/logs/');

    define('ENABLE_DISCORD_WEBHOOK', getenv('FINDINGS_WEBHOOK') !== '0');
    define('URL_DISCORD_WEBHOOK', getenv('FINDINGS_DISCORD_URL') ?: 'http://127.0.0.1:1/unreachable');
    define('WEBHOOK_USERNAME', 'test-bot');

    define('ENABLE_QUEUE', true);
    define('QUEUE_DIR', $tmp.'/queue/');
    define('DISCORD_MAX_PER_FLUSH', (int)(getenv('FINDINGS_DISCORD_MAX') ?: 5));
    define('REPORTS_MAX_PER_FLUSH', (int)(getenv('FINDINGS_REPORTS_MAX') ?: 50));

    define('CORS_ALLOW_ORIGIN', '*');
    define('CORS_ALLOW_HEADERS', 'Content-Type, Authorization');
    define('TRUSTED_PROXIES', []);

    require __DIR__.'/../../../src/cron/flush.php';
