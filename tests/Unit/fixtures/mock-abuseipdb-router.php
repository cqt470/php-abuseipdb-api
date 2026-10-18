<?php
    /**
     * Mock AbuseIPDB router for the PHP built-in server.
     *
     * Start manually:
     *     php -S 127.0.0.1:17490 tests/Unit/fixtures/mock-abuseipdb-router.php
     *
     * Or let SecurityGetIpInfoTest start/stop it automatically.
     */

    $ip = $_GET['ipAddress'] ?? '';

    // Log the raw request URI so the "API key not in URL" regression test can inspect it.
    file_put_contents(__DIR__ . '/last_request.log', $_SERVER['REQUEST_URI'] . PHP_EOL);

    switch ($ip) {
        case '203.0.113.4': // 429 rate limit
            http_response_code(429);
            echo json_encode(['errors' => [['detail' => 'Rate limited', 'status' => 429]]]);
            break;

        case '203.0.113.5': // 500 server error
            http_response_code(500);
            echo json_encode(['errors' => [['detail' => 'Internal error', 'status' => 500]]]);
            break;

        case '203.0.113.6': // 401 invalid API key
            http_response_code(401);
            echo json_encode(['errors' => [['detail' => 'Invalid API key', 'status' => 401]]]);
            break;

        default: // 200 OK, payload shape expected by Security::add_entry()
            http_response_code(200);
            echo json_encode([
                'data' => [
                    'ipAddress'            => $ip,
                    'abuseConfidenceScore' => 0,
                    'countryCode'          => 'ZZ',
                ],
            ]);
    }