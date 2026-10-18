<?php
    declare(strict_types=1);

    namespace Zexa\Tests\Unit;

    use PHPUnit\Framework\TestCase;
    use Zexa\Includes\Cache;

    /**
     * Bug #2: Cache::check_for_entry() evicts the WRONG entry.
     *
     * Reproducer: seed the cache as [stale, valid] (stale FIRST in the list),
     * then call check_for_entry("ipAddress", valid_ip).
     *
     * Current behavior: the loop finds the stale entry, calls
     * delete_entry("ipAddress", valid_ip), which removes the VALID entry,
     * and returns false — so the stale entry survives and the valid one is lost.
     *
     * Correct behavior: only the stale entry must be removed, and the valid
     * entry must survive.
     */
    final class CacheTest extends TestCase
    {
        private const VALID_IP = '203.0.113.43';
        private const STALE_IP = '203.0.113.42';

        private string $cache_file;

        protected function setUp(): void
        {
            // Cache::__construct() throws if the file does not exist,
            // so we must create it before every test.
            $this->cache_file = \CACHE_FILE;
            $dir = dirname($this->cache_file);
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            file_put_contents($this->cache_file, '[]');
        }

        protected function tearDown(): void
        {
            file_put_contents($this->cache_file, '[]');
        }

        // ------------------------------------------------------------------
        // Bug #2 — core
        // ------------------------------------------------------------------

        public function test_check_for_entry_keeps_valid_entry_when_stale_one_exists(): void
        {
            // Stale entry must come FIRST in the array to reproduce the bug.
            $this->seedCache([
                $this->makeEntry(self::STALE_IP, time() - \CACHE_TTL - 100),
                $this->makeEntry(self::VALID_IP, time()),
            ]);

            $cache = new Cache($this->cache_file);
            $cache->check_for_entry('ipAddress', self::VALID_IP);

            $this->assertTrue(
                $this->cacheContainsIp(self::VALID_IP),
                'The valid entry MUST still be in the cache after check_for_entry().'
            );
        }

        public function test_check_for_entry_evicts_the_stale_entry(): void
        {
            $this->seedCache([
                $this->makeEntry(self::STALE_IP, time() - \CACHE_TTL - 100),
                $this->makeEntry(self::VALID_IP, time()),
            ]);

            $cache = new Cache($this->cache_file);
            $cache->check_for_entry('ipAddress', self::VALID_IP);

            $this->assertFalse(
                $this->cacheContainsIp(self::STALE_IP),
                'The stale entry MUST be evicted from the cache.'
            );
        }

        // ------------------------------------------------------------------
        // Regression guards
        // ------------------------------------------------------------------

        public function test_check_for_entry_returns_true_for_valid_entry(): void
        {
            $this->seedCache([$this->makeEntry(self::VALID_IP, time())]);

            $cache = new Cache($this->cache_file);
            $this->assertTrue($cache->check_for_entry('ipAddress', self::VALID_IP));
        }

        public function test_check_for_entry_returns_false_when_not_present(): void
        {
            $this->seedCache([]);

            $cache = new Cache($this->cache_file);
            $this->assertFalse($cache->check_for_entry('ipAddress', '203.0.113.99'));
        }

        public function test_add_entry_persists_to_file(): void
        {
            $cache = new Cache($this->cache_file);
            $cache->add_entry([
                'data' => [
                    'ipAddress'            => self::VALID_IP,
                    'abuseConfidenceScore' => 0,
                ],
            ]);

            $this->assertTrue($this->cacheContainsIp(self::VALID_IP));
        }

        public function test_get_entry_returns_entry_when_present(): void
        {
            $this->seedCache([$this->makeEntry(self::VALID_IP, time())]);

            $cache = new Cache($this->cache_file);
            $entry = $cache->get_entry('ipAddress', self::VALID_IP);

            $this->assertIsArray($entry);
            $this->assertSame(self::VALID_IP, $entry['ipAddress']);
        }

        // ------------------------------------------------------------------
        // Helpers
        // ------------------------------------------------------------------

        private function makeEntry(string $ip, int $added_at): array
        {
            return [
                'ipAddress'            => $ip,
                'abuseConfidenceScore' => 0,
                'added_at'             => $added_at,
            ];
        }

        private function seedCache(array $entries): void
        {
            file_put_contents($this->cache_file, json_encode(array_values($entries)));
        }

        private function cacheContainsIp(string $ip): bool
        {
            $data = json_decode((string) file_get_contents($this->cache_file), true) ?? [];
            foreach ($data as $entry) {
                if (is_array($entry) && ($entry['ipAddress'] ?? null) === $ip) {
                    return true;
                }
            }
            return false;
        }
    }