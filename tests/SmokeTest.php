<?php
    declare(strict_types=1);

    namespace Tests;

    include_once __DIR__."/bootstrap.php";
    include_once __DIR__."/../tools/phpunit-10.phar";

    use PHPUnit\Framework\TestCase;

    final class SmokeTest extends TestCase{
        public function test_constants_are_the_test_ones(): void{
            $this->assertSame('test', ENVIRONMENT);
            $this->assertStringStartsWith('/tmp/', CACHE_FILE, 'Cache must be isolated in tests/');
            $this->assertStringContainsString('127.0.0.1', ABUSEIPDB_BASE_URL, 'Must point to stub');
            $this->assertFalse(ENABLE_DISCORD_WEBHOOK);
        }

        public function test_source_classes_load(): void{
            $this->assertTrue(class_exists(\Zexa\Includes\Security::class));
            $this->assertTrue(class_exists(\Zexa\Includes\Cache::class));
            $this->assertTrue(class_exists(\Zexa\Includes\Endpoint::class));
            $this->assertTrue(class_exists(\Zexa\Includes\Logger::class));
        }
    }