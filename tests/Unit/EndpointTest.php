<?php
    declare(strict_types=1);

    namespace Zexa\Tests\Unit;

    use PHPUnit\Framework\TestCase;
    use ReflectionClass;
    use Zexa\Includes\Endpoint;

    /**
     * Bug #3: Endpoint::get_token() crashes when Authorization is missing.
     *
     * Two problems in the current code:
     *   1. getallheaders() does not exist on the CLI SAPI (where PHPUnit runs),
     *      so the call is a fatal Error.
     *   2. If getallheaders() exists but the header is absent, and
     *      $_SERVER['HTTP_AUTHORIZATION'] is also unset, the code triggers
     *      "Undefined array key" and passes null to preg_match().
     *
     * We invoke get_token() via reflection and skip the constructor
     * (the constructor calls handle_cors() which sends headers and may exit()).
     */
    final class EndpointTest extends TestCase
    {
        private ?string $saved_http_auth = null;

        protected function setUp(): void
        {
            $this->saved_http_auth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
            unset($_SERVER['HTTP_AUTHORIZATION']);
        }

        protected function tearDown(): void
        {
            if ($this->saved_http_auth === null) unset($_SERVER['HTTP_AUTHORIZATION']);
            else                                 $_SERVER['HTTP_AUTHORIZATION'] = $this->saved_http_auth;
        }

        // ------------------------------------------------------------------
        // Bug #3 — core
        // ------------------------------------------------------------------

        public function test_get_token_does_not_crash_without_authorization_header(): void
        {
            $token = $this->invoke_get_token();

            $this->assertNull(
                $token,
                'get_token() must return null (not crash) when no Authorization is present.'
            );
        }

        public function test_get_token_does_not_crash_with_empty_authorization_header(): void
        {
            $_SERVER['HTTP_AUTHORIZATION'] = '';

            $this->assertNull($this->invoke_get_token());
        }

        // ------------------------------------------------------------------
        // Happy-path regressions
        // ------------------------------------------------------------------

        public function test_get_token_extracts_bearer_token(): void
        {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc123';

            $this->assertSame('abc123', $this->invoke_get_token());
        }

        public function test_get_token_ignores_non_bearer_header(): void
        {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

            $this->assertNull(
                $this->invoke_get_token(),
                'Only RFC 6750 Bearer tokens must be accepted.'
            );
        }

        // ------------------------------------------------------------------
        // Helper: call protected get_token() without running the constructor
        // ------------------------------------------------------------------

        private function invoke_get_token(): ?string
        {
            $rc = new ReflectionClass(Endpoint::class);
            $ep = $rc->newInstanceWithoutConstructor();
            $method = $rc->getMethod('get_token');
            // PHP 8.1+ makes all methods accessible via reflection.
            return $method->invoke($ep);
        }
    }