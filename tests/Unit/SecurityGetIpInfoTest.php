<?php
    declare(strict_types=1);

    namespace Zexa\Tests\Unit;

    use PHPUnit\Framework\TestCase;
    use Zexa\Includes\Security;

    final class SecurityGetIpInfoTest extends TestCase
    {
        private const IP_429 = '203.0.113.4';
        private const IP_500 = '203.0.113.5';
        private const IP_401 = '203.0.113.6';
        private const IP_OK  = '203.0.113.10';

        /** @var resource|null */
        private static $server_proc = null;
        private static bool $server_owned = false;

        public static function setUpBeforeClass(): void
        {
            if (!function_exists('proc_open')) {
                self::markTestSkipped('proc_open is disabled, cannot start mock server.');
            }

            $parsed = parse_url(\ABUSEIPDB_BASE_URL);
            if (!$parsed || empty($parsed['host']) || empty($parsed['scheme'])) {
                return;
            }

            $host = $parsed['host'];
            $port = $parsed['port'] ?? (($parsed['scheme'] === 'https') ? 443 : 80);

            // If a server is already listening, don't try to start another one.
            if (self::probe_server($host, $port)) {
                return;
            }

            $router = __DIR__ . '/fixtures/mock-abuseipdb-router.php';
            $cmd = sprintf('php -S %s:%d %s', $host, $port, escapeshellarg($router));

            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            self::$server_proc = proc_open($cmd, $descriptors, $pipes);
            self::$server_owned = is_resource(self::$server_proc);

            // Give the server up to 5 seconds to bind the port (CI runners can be slow).
            $deadline = microtime(true) + 5.0;
            while (microtime(true) < $deadline) {
                if (self::probe_server($host, $port)) {
                    return;
                }
                usleep(100_000); // 100ms
            }
        }

        public static function tearDownAfterClass(): void
        {
            if (self::$server_owned && is_resource(self::$server_proc)) {
                proc_terminate(self::$server_proc);
                proc_close(self::$server_proc);
            }
            self::$server_proc = null;
            self::$server_owned = false;
        }

        protected function setUp(): void
        {
            $base = \ABUSEIPDB_BASE_URL;
            $parsed = parse_url($base);
            if (!$parsed || empty($parsed['host']) || empty($parsed['scheme'])) {
                $this->markTestSkipped(
                    'ABUSEIPDB_BASE_URL must be a full URL (e.g. http://127.0.0.1:17490). Got: ' . $base
                );
            }

            $dir = dirname(\CACHE_FILE);
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            file_put_contents(\CACHE_FILE, '[]');

            $log = __DIR__ . '/fixtures/last_request.log';
            if (file_exists($log)) unlink($log);
        }

        /**
         * @dataProvider non_2xx_provider
         */
        public function test_does_not_cache_non_2xx_response(string $ip): void
        {
            Security::get_ip_info($ip, true);

            $this->assertEmpty(
                $this->readCache(),
                "A non-2xx response for $ip MUST NOT be written to the cache."
            );
        }

        public static function non_2xx_provider(): array
        {
            return [
                '429 rate limit'   => [self::IP_429],
                '500 server error' => [self::IP_500],
                '401 auth error'   => [self::IP_401],
            ];
        }

        public function test_caches_2xx_response(): void
        {
            Security::get_ip_info(self::IP_OK, true);

            $this->assertTrue(
                $this->cacheContainsIp(self::IP_OK),
                'A 2xx response SHOULD be written to the cache.'
            );
        }

        public function test_api_key_not_in_request_url(): void
        {
            Security::get_ip_info(self::IP_OK, true);

            $log = __DIR__ . '/fixtures/last_request.log';
            $this->assertFileExists($log, 'The mock server did not log any request.');

            $url = trim((string) file_get_contents($log));

            $this->assertStringNotContainsString(
                \API_KEY_ABUSEIPDB,
                $url,
                'The API key must never appear in the request URL.'
            );
            $this->assertStringNotContainsString(
                'key=',
                strtolower($url),
                'The API key must be sent via the Key header, not as a query string.'
            );
        }

        // ------------------------------------------------------------------
        // Helpers
        // ------------------------------------------------------------------

        private static function probe_server(string $host, int $port): bool
        {
            $probe = false;
            // Temporarily suppress warnings so PHPUnit's error handler doesn't throw on connection refused.
            $previous = set_error_handler(static fn() => true);
            try {
                $probe = fsockopen($host, $port, $errno, $errstr, 0.1);
            } finally {
                restore_error_handler();
            }

            if ($probe) {
                fclose($probe);
                return true;
            }
            return false;
        }

        private function readCache(): array
        {
            if (!file_exists(\CACHE_FILE)) return [];
            return json_decode((string) file_get_contents(\CACHE_FILE), true) ?? [];
        }

        private function cacheContainsIp(string $ip): bool
        {
            foreach ($this->readCache() as $entry) {
                if (is_array($entry) && ($entry['ipAddress'] ?? null) === $ip) {
                    return true;
                }
            }
            return false;
        }
    }