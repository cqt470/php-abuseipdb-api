<?php
declare(strict_types=1);

namespace Zexa\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Zexa\Includes\Security;

final class SecurityTest extends TestCase
{
    private ?string $savedRemoteAddr = null;
    private ?string $savedCfIp       = null;
    private ?string $savedXff        = null;

    protected function setUp(): void
    {
        $this->savedRemoteAddr = $_SERVER['REMOTE_ADDR']              ?? null;
        $this->savedCfIp       = $_SERVER['HTTP_CF_CONNECTING_IP']    ?? null;
        $this->savedXff        = $_SERVER['HTTP_X_FORWARDED_FOR']     ?? null;
    }

    protected function tearDown(): void
    {
        $this->restore('REMOTE_ADDR',            $this->savedRemoteAddr);
        $this->restore('HTTP_CF_CONNECTING_IP',  $this->savedCfIp);
        $this->restore('HTTP_X_FORWARDED_FOR',   $this->savedXff);
    }

    private function restore(string $key, ?string $value): void
    {
        if($value === null) unset($_SERVER[$key]);
        else                $_SERVER[$key] = $value;
    }

    public function test_uses_remote_addr_when_directly_connected(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
        $this->assertSame('203.0.113.42', Security::get_ip());
    }

    public function test_ignores_cf_header_from_non_cloudflare_peer(): void
    {
        // Direct connection, attacker forges the CF header.
        $_SERVER['REMOTE_ADDR']           = '203.0.113.42';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '1.2.3.4';

        $this->assertSame(
            '203.0.113.42',
            Security::get_ip(),
            'CF-Connecting-IP must be ignored when REMOTE_ADDR is not a trusted proxy'
        );
    }

    public function test_ignores_xff_from_non_cloudflare_peer(): void
    {
        $_SERVER['REMOTE_ADDR']         = '203.0.113.42';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 5.6.7.8';

        $this->assertSame('203.0.113.42', Security::get_ip());
    }

    public function test_trusts_cf_header_from_cloudflare_peer(): void
    {
        $_SERVER['REMOTE_ADDR']           = '173.245.48.1'; // Cloudflare range
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.42';

        $this->assertSame('203.0.113.42', Security::get_ip());
    }

    public function test_ignores_invalid_cf_header_from_cloudflare_peer(): void
    {
        $_SERVER['REMOTE_ADDR']           = '173.245.48.1';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = 'not-an-ip';

        $this->assertSame('173.245.48.1', Security::get_ip());
    }

    public function test_falls_back_to_loopback_without_remote_addr(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CF_CONNECTING_IP']);
        $this->assertSame('127.0.0.1', Security::get_ip());
    }

    public function test_validate_ip_rejects_garbage(): void
    {
        $this->assertFalse(Security::validate_ip('not-an-ip'));
    }

    public function test_validate_ip_accepts_v4_and_v6(): void
    {
        $this->assertTrue(Security::validate_ip('203.0.113.42'));
        $this->assertTrue(Security::validate_ip('2001:db8::1'));
    }

    /**
     * @dataProvider cidr_provider
     */
    public function test_ip_in_cidr(string $ip, string $cidr, bool $expected): void
    {
        $this->assertSame($expected, Security::ip_in_cidr($ip, $cidr));
    }

    public static function cidr_provider(): array
    {
        return [
            'v4 inside'          => ['173.245.48.42', '173.245.48.0/20', true],
            'v4 outside'         => ['173.245.64.0', '173.245.48.0/20', false],
            'v4 exact'           => ['203.0.113.42', '203.0.113.42/32', true],
            'v4 family mismatch' => ['2001:db8::1',   '173.245.48.0/20', false],
            'v6 inside'          => ['2606:4700::1',  '2606:4700::/32',  true],
            'v6 outside'         => ['2a00:1450::1',  '2606:4700::/32',  false],
            'bare v4 exact'      => ['203.0.113.42', '203.0.113.42',    true],
        ];
    }
}