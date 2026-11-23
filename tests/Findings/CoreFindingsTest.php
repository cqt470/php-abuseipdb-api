<?php
    declare(strict_types=1);

    namespace Zexa\Tests\Findings;

    require_once __DIR__.'/ServerHelper.php';

    use PHPUnit\Framework\TestCase;
    use Zexa\Includes\Cache;
    use Zexa\Includes\Logger;
    use Zexa\Includes\log_priority;
    use Zexa\Includes\Security;

    /**
     * Targeted tests on Cache / Security / Logger, run in-process.
     * Every test asserts the CORRECT behaviour: a failure is a finding.
     */
    final class CoreFindingsTest extends TestCase
    {
        public static function setUpBeforeClass(): void
        {
            // the unit mock that \ABUSEIPDB_BASE_URL points to
            ServerHelper::start(17490, dirname(__DIR__).'/Unit/fixtures/mock-abuseipdb-router.php');
        }

        public static function tearDownAfterClass(): void
        {
            ServerHelper::stop_all();
        }

        protected function setUp(): void
        {
            file_put_contents(\CACHE_FILE, '[]');
        }

        /** A cache hit must return the same structure as a cache miss. */
        public function test_cache_hit_returns_same_shape_as_miss(): void
        {
            $miss = Security::get_ip_info('203.0.113.20');
            $hit  = Security::get_ip_info('203.0.113.20');

            $this->assertSame(
                array_keys($miss), array_keys($hit),
                'miss keys: '.json_encode(array_keys($miss)).' / hit keys: '.json_encode(array_keys($hit))
            );
        }

        /** Refreshing an already-cached IP must not print anything (it would corrupt the JSON response). */
        public function test_skip_cache_on_cached_ip_prints_nothing(): void
        {
            Security::get_ip_info('203.0.113.21');
            $this->expectOutputString('');
            Security::get_ip_info('203.0.113.21', true);
        }

        /** Refreshing must actually update the cached data (new added_at). */
        public function test_skip_cache_refreshes_cached_entry(): void
        {
            Security::get_ip_info('203.0.113.22');
            $data = json_decode(file_get_contents(\CACHE_FILE), true);
            $data[0]['added_at'] = time() - 600;
            file_put_contents(\CACHE_FILE, json_encode($data));

            ob_start();
            Security::get_ip_info('203.0.113.22', true);
            ob_end_clean();

            $after = json_decode(file_get_contents(\CACHE_FILE), true);
            $this->assertGreaterThan(time() - 60, array_values($after)[0]['added_at']);
        }

        /** After a delete, the cache file must still be a JSON list, not an object. */
        public function test_delete_entry_keeps_json_list(): void
        {
            $now = time();
            file_put_contents(\CACHE_FILE, json_encode([
                ['ipAddress' => '198.51.100.1', 'added_at' => $now],
                ['ipAddress' => '198.51.100.2', 'added_at' => $now],
                ['ipAddress' => '198.51.100.3', 'added_at' => $now],
            ]));

            (new Cache(\CACHE_FILE))->delete_entry('ipAddress', '198.51.100.1');

            $raw = file_get_contents(\CACHE_FILE);
            $this->assertStringStartsWith('[', $raw, "cache file became: $raw");
        }

        /** An entry without "added_at" (old format / hand-edited) must not crash the request. */
        public function test_entry_without_added_at_does_not_throw(): void
        {
            file_put_contents(\CACHE_FILE, json_encode([['ipAddress' => '198.51.100.9']]));
            $this->assertFalse((new Cache(\CACHE_FILE))->check_for_entry('ipAddress', '198.51.100.10'));
        }

        /** A corrupted cache file must not be silently overwritten (all cached data lost). */
        public function test_corrupted_cache_is_not_silently_wiped(): void
        {
            file_put_contents(\CACHE_FILE, '[{"ipAddress":"198.51.100.5","added_at":'.time().'},');
            (new Cache(\CACHE_FILE))->add_entry(['data' => ['ipAddress' => '198.51.100.6']]);

            $this->assertStringContainsString('198.51.100.5', file_get_contents(\CACHE_FILE));
        }

        /** Concurrent writers must not lose entries (read-modify-write without lock). */
        public function test_concurrent_add_entry_does_not_lose_entries(): void
        {
            $file = ServerHelper::tmp().'/concurrency.json';
            file_put_contents($file, '[]');
            $writers = 8; $per = 25;

            $procs = [];
            for ($w = 1; $w <= $writers; $w++) {
                $procs[] = proc_open(
                    [PHP_BINARY, __DIR__.'/fixtures/cache-writer.php', $file, (string)$w, (string)$per],
                    [1 => ['file', ServerHelper::tmp().'/writer.log', 'a'], 2 => ['file', ServerHelper::tmp().'/writer.log', 'a']],
                    $pipes
                );
            }
            foreach ($procs as $p) proc_close($p);

            $data = json_decode(file_get_contents($file), true) ?? [];
            $this->assertCount($writers * $per, $data, 'entries lost by concurrent writers');
        }

        /** Logger must work where REQUEST_METHOD is not set (CLI, cron, workers). */
        public function test_logger_works_without_request_method(): void
        {
            $saved = $_SERVER['REQUEST_METHOD'];
            unset($_SERVER['REQUEST_METHOD']);
            try {
                (new Logger(\LOGS_DIR))->log('findings', log_priority::info, 'cli line');
                $this->assertTrue(true);
            } finally {
                $_SERVER['REQUEST_METHOD'] = $saved;
            }
        }

        /** The project's error handler must honour the "@" operator. */
        public function test_error_handler_respects_at_operator(): void
        {
            $this->assertFalse(@file_get_contents(ServerHelper::tmp().'/does-not-exist.txt'));
        }

        /** "@" must suppress warnings: ip_in_cidr on garbage must return false, not throw. */
        public function test_ip_in_cidr_with_garbage_returns_false(): void
        {
            $this->assertFalse(Security::ip_in_cidr('not-an-ip', '10.0.0.0/8'));
        }

        /** A malformed TRUSTED_PROXIES entry must not crash every request. */
        public function test_ip_in_cidr_with_garbage_cidr_returns_false(): void
        {
            $this->assertFalse(Security::ip_in_cidr('10.0.0.1', 'garbage/8'));
        }
    }
