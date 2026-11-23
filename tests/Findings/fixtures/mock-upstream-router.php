<?php
    /**
     * Mock AbuseIPDB upstream used by the findings suite.
     * Unlike the unit mock, it canonicalises IPs like the real API does
     * and records every request in FINDINGS_HITS_FILE.
     */

    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $hits = getenv('FINDINGS_HITS_FILE');
    if ($hits) {
        file_put_contents($hits, $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI']."\n", FILE_APPEND | LOCK_EX);
    }

    header('Content-Type: application/json');

    function fail(int $code, string $detail): void {
        http_response_code($code);
        echo json_encode(['errors' => [['detail' => $detail, 'status' => $code]]]);
    }

    if ($path === '/check') {
        $bin = @inet_pton($_GET['ipAddress'] ?? '');
        if ($bin === false) { fail(422, 'The ipAddress field must be a valid IP address.'); return; }

        echo json_encode(['data' => [
            'ipAddress'            => inet_ntop($bin), // real API returns the canonical form
            'isPublic'             => true,
            'abuseConfidenceScore' => 0,
            'countryCode'          => 'ZZ',
            'reports'              => [
                ['reportedAt' => gmdate('Y-m-d\TH:i:sP', time() - 600), 'comment' => 'ssh', 'categories' => [18, 22],
                 'reporterId' => 7, 'reporterCountryCode' => 'DE', 'reporterCountryName' => 'Germany'],
            ],
        ]]);
        return;
    }

    if ($path === '/report') {
        parse_str(file_get_contents('php://input'), $p);
        if (($p['ip'] ?? '') === '203.0.113.99') { fail(429, 'Daily rate limit of 1000 requests exceeded.'); return; }
        if (($p['ip'] ?? '') === '203.0.113.97') { fail(429, 'You can only report the same IP address (`203.0.113.97`) once in 15 minutes.'); return; }
        if (($p['ip'] ?? '') === '203.0.113.98') { fail(422, 'The comment may not be greater than 1024 characters.'); return; }

        echo json_encode(['data' => ['ipAddress' => $p['ip'] ?? null, 'abuseConfidenceScore' => 100]]);
        return;
    }

    fail(404, 'Not found');
