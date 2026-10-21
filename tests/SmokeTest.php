<?php
    declare(strict_types=1);

    namespace Tests;

    use PHPUnit\Framework\TestCase;

    final class SmokeTest extends TestCase{
        public function test_constants_are_the_test_ones(): void{
            $this->assertSame('test', ENVIRONMENT);
            $this->assertStringContainsString('/tests/tmp/', CACHE_FILE, 'Cache must be isolated');
            $this->assertStringContainsString('127.0.0.1', ABUSEIPDB_BASE_URL, 'Must point to stub');
            $this->assertFalse(ENABLE_DISCORD_WEBHOOK);
        }

        public function test_source_classes_load(): void{
            $this->assertTrue(class_exists(\Zexa\Includes\Security::class));
            $this->assertTrue(class_exists(\Zexa\Includes\Cache::class));
            $this->assertTrue(class_exists(\Zexa\Includes\Endpoint::class), 'Endpoint class not found'); 
            $this->assertTrue(class_exists(\Zexa\Includes\Logger::class));
        }
    }