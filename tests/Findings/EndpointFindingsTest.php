<?php
    declare(strict_types=1);

    namespace Zexa\Tests\Findings;

    require_once __DIR__.'/ServerHelper.php';

    use PHPUnit\Framework\TestCase;

    /**
     * Black-box tests on src/api/abuseipdb.php served over HTTP.
     * Every test asserts the CORRECT behaviour: a failure is a finding.
     */
    final class EndpointFindingsTest extends TestCase
    {
        private const UPSTREAM    = 17495;
        private const NORMAL      = 17496; // standard config
        private const WEBHOOK     = 17497; // Discord enabled but unreachable
        private const PLACEHOLDER = 17498; // API_KEY_CUSTOM left as "YOUR_KEY_HERE"
        private const DEAD_UP     = 17499; // AbuseIPDB unreachable

        private const KEY = 'test-custom-key-0000000000000000';

        private static string $hits;

        public static function setUpBeforeClass(): void
        {
            try {
                self::start_servers();
            } catch (\Throwable $e) {
                ServerHelper::stop_all(); // tearDownAfterClass is not called when this fails
                throw $e;
            }
        }

        private static function start_servers(): void
        {
            $tmp = ServerHelper::tmp();
            self::$hits = "$tmp/upstream-hits.log";
            file_put_contents(self::$hits, '');

            ServerHelper::start(self::UPSTREAM, __DIR__.'/fixtures/mock-upstream-router.php', ['FINDINGS_HITS_FILE' => self::$hits]);

            $up = 'http://127.0.0.1:'.self::UPSTREAM;
            $configs = [
                self::NORMAL      => ['FINDINGS_BASE_URL' => $up],
                self::WEBHOOK     => ['FINDINGS_BASE_URL' => $up, 'FINDINGS_WEBHOOK' => '1'],
                self::PLACEHOLDER => ['FINDINGS_BASE_URL' => $up, 'FINDINGS_CUSTOM_KEY' => 'YOUR_KEY_HERE'],
                self::DEAD_UP     => ['FINDINGS_BASE_URL' => 'http://127.0.0.1:1'],
            ];
            foreach ($configs as $port => $env) {
                $dir = "$tmp/$port";
                if (!is_dir("$dir/logs")) mkdir("$dir/logs", 0777, true);
                file_put_contents("$dir/ips.json", '[]');
                ServerHelper::start($port, __DIR__.'/fixtures/endpoint-router.php', $env + ['FINDINGS_TMP' => $dir]);
            }
        }

        public static function tearDownAfterClass(): void
        {
            ServerHelper::stop_all();
        }

        private static function call(int $port, array $body, ?string $token = self::KEY): array
        {
            return ServerHelper::request($port, $body, $token === null ? [] : ['Authorization' => "Bearer $token"]);
        }

        private static function upstream_hits(): int
        {
            return count(file(self::$hits, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        }

        private static function describe(array $r): string
        {
            return "HTTP {$r['status']} body: {$r['body']}";
        }

        // ---------- sanity: these should pass ----------

        public function test_sanity_check_works_with_bearer_token(): void
        {
            $r = self::call(self::NORMAL, ['action' => 'check', 'ip' => '198.51.100.7']);
            $this->assertSame(200, $r['status'], self::describe($r));
            $this->assertTrue($r['json']['success']);
        }

        public function test_sanity_wrong_token_is_403(): void
        {
            $r = self::call(self::NORMAL, ['action' => 'check', 'ip' => '198.51.100.7'], 'wrong');
            $this->assertSame(403, $r['status'], self::describe($r));
        }

        public function test_sanity_invalid_ip_is_400(): void
        {
            $r = self::call(self::NORMAL, ['action' => 'check', 'ip' => '999.1.1.1']);
            $this->assertSame(400, $r['status'], self::describe($r));
        }

        // ---------- findings ----------

        /** The client must get the same JSON structure whether the IP was cached or not. */
        public function test_check_response_shape_is_stable_across_cache_hit(): void
        {
            $first  = self::call(self::NORMAL, ['action' => 'check', 'ip' => '198.51.100.30']);
            $second = self::call(self::NORMAL, ['action' => 'check', 'ip' => '198.51.100.30']);

            $this->assertSame(
                array_keys($first['json']['parameters']['info']),
                array_keys($second['json']['parameters']['info']),
                "1st: {$first['body']}\n2nd: {$second['body']}"
            );
        }

        /** IPv6 written in non-canonical form must be served from cache the second time. */
        public function test_non_canonical_ipv6_hits_the_cache(): void
        {
            self::call(self::NORMAL, ['action' => 'check', 'ip' => '2001:DB8:0:0::AB']);
            $before = self::upstream_hits();
            self::call(self::NORMAL, ['action' => 'check', 'ip' => '2001:DB8:0:0::AB']);

            $this->assertSame($before, self::upstream_hits(), 'second check went to AbuseIPDB again (cache miss)');
        }

        /** A perfectly valid report must succeed. */
        public function test_valid_report_succeeds(): void
        {
            $r = self::call(self::NORMAL, ['action' => 'report', 'ip' => '198.51.100.43', 'categories' => [18, 22], 'comment' => 'ssh brute force']);
            $this->assertSame(200, $r['status'], self::describe($r));
        }

        /** When AbuseIPDB rejects a report, the proxy must not answer success (should mirror the 4xx/5xx). */
        public function test_report_upstream_error_is_not_success(): void
        {
            $r = self::call(self::NORMAL, ['action' => 'report', 'ip' => '203.0.113.99', 'categories' => [18], 'comment' => 'ssh brute force']);
            $this->assertFalse($r['json']['success'] ?? true, self::describe($r));
        }

        /** Bad input must give a 400, not a 500. */
        public function test_report_nested_categories_is_400(): void
        {
            $r = self::call(self::NORMAL, ['action' => 'report', 'ip' => '198.51.100.40', 'categories' => [[18]], 'comment' => 'x']);
            $this->assertSame(400, $r['status'], self::describe($r));
        }

        /** Categories must be validated (AbuseIPDB ids are 1..23). */
        public function test_report_invalid_category_is_400(): void
        {
            $r = self::call(self::NORMAL, ['action' => 'report', 'ip' => '198.51.100.41', 'categories' => ['abc', 999], 'comment' => 'x']);
            $this->assertSame(400, $r['status'], self::describe($r));
        }

        /** The docs say ISO 8601: free text like "tomorrow" must be rejected. */
        public function test_report_non_iso_timestamp_is_400(): void
        {
            $r = self::call(self::NORMAL, ['action' => 'report', 'ip' => '198.51.100.42', 'categories' => [18], 'comment' => 'x', 'timestamp' => 'tomorrow']);
            $this->assertSame(400, $r['status'], self::describe($r));
        }

        /** AbuseIPDB unreachable must give a gateway error, not a generic 500. */
        public function test_check_with_upstream_down_is_gateway_error(): void
        {
            $r = self::call(self::DEAD_UP, ['action' => 'check', 'ip' => '198.51.100.50']);
            $this->assertContains($r['status'], [502, 503, 504], self::describe($r));
        }

        /** Discord down must not turn a failed login into a 500. */
        public function test_failed_auth_with_discord_down_is_still_403(): void
        {
            $r = self::call(self::WEBHOOK, ['action' => 'check', 'ip' => '198.51.100.60'], 'wrong');
            $this->assertSame(403, $r['status'], self::describe($r));
        }

        /** Discord down must not break a valid check (the notification is a side effect). */
        public function test_check_with_discord_down_still_succeeds(): void
        {
            $r = self::call(self::WEBHOOK, ['action' => 'check', 'ip' => '198.51.100.61']);
            $this->assertSame(200, $r['status'], self::describe($r));
        }

        /** The example placeholder key must never authenticate anybody. */
        public function test_placeholder_custom_key_is_rejected(): void
        {
            $r = self::call(self::PLACEHOLDER, ['action' => 'check', 'ip' => '198.51.100.70'], 'YOUR_KEY_HERE');
            $this->assertNotSame(200, $r['status'], self::describe($r));
        }
    }
