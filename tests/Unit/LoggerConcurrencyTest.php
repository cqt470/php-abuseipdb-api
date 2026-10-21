<?php

    declare(strict_types=1);

    namespace Zexa\Tests\Unit;

    use PHPUnit\Framework\Attributes\Group;
    use PHPUnit\Framework\TestCase;
    use Zexa\Includes\Logger;
    use Zexa\Includes\log_priority;

    #[Group('concurrency')]
    #[Group('logger')]
    final class LoggerConcurrencyTest extends TestCase
    {
        private const WORKERS          = 8;
        private const LINES_PER_WORKER = 250;
        private const PAYLOAD_BYTES    = 2048;

        private string $tmpDir;

        protected function setUp(): void
        {
            $this->tmpDir = sys_get_temp_dir()
                . DIRECTORY_SEPARATOR
                . 'php-abuseipdb-logger-concurrency-'
                . bin2hex(random_bytes(8));

            if (!mkdir($this->tmpDir, 0o777, true) && !is_dir($this->tmpDir)) {
                self::fail("Impossibile creare la directory temporanea: {$this->tmpDir}");
            }
        }

        protected function tearDown(): void
        {
            if (!is_dir($this->tmpDir)) {
                return;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $entry) {
                $entry->isDir()
                    ? @rmdir($entry->getPathname())
                    : @unlink($entry->getPathname());
            }

            @rmdir($this->tmpDir);
        }

        public function testConcurrentLogWritesDoNotCorruptLines(): void
        {
            $logDir     = $this->tmpDir;
            $logFile    = $logDir . DIRECTORY_SEPARATOR . 'test.log';
            $workerPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'logger_worker.php';

            file_put_contents($workerPath, self::workerScript());
            $this->assertFileExists($workerPath);

            $processes = [];

            for ($worker = 0; $worker < self::WORKERS; $worker++) {
                $descriptors = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];

                $command = [
                    PHP_BINARY,
                    '-d', 'opcache.enable_cli=0',
                    $workerPath,
                    $logDir,
                    (string) $worker,
                    (string) self::LINES_PER_WORKER,
                    (string) self::PAYLOAD_BYTES,
                ];

                $process = proc_open($command, $descriptors, $pipes);
                $this->assertIsResource($process, "proc_open fallita per il worker {$worker}");
                $processes[] = [$process, $pipes];
            }

            foreach ($processes as $index => [$process, $pipes]) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);

                $exitCode = proc_close($process);

                $this->assertSame(
                    0,
                    $exitCode,
                    "Worker {$index} terminato con codice {$exitCode}.\n"
                    . "stdout: {$stdout}\nstderr: {$stderr}"
                );
            }

            $this->assertFileExists($logFile);

            // Il formato atteso è quello prodotto da Logger::log():
            // [PRIORITY] ISO8601 - METHOD - text (Origin: ip)
            $linePattern = '/^'
                . '\[(?<priority>[A-Z]+)\]'
                . ' (?<ts>\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2})'
                . ' - (?<method>[A-Z]+)'
                . ' - worker=(?<worker>\d+) line=(?<line>\d+) payload=(?<payload>x*)$/';

            $expectedPayload = str_repeat('x', self::PAYLOAD_BYTES);
            $expectedLines   = self::WORKERS * self::LINES_PER_WORKER;

            $handle = fopen($logFile, 'rb');
            $this->assertIsResource($handle);

            $seen       = [];
            $lineNumber = 0;

            try {
                while (($raw = fgets($handle)) !== false) {
                    $lineNumber++;
                    $line = rtrim($raw, "\r\n");

                    $this->assertMatchesRegularExpression(
                        $linePattern,
                        $line,
                        "Riga {$lineNumber} malformata o interlacciata: "
                        . substr($line, 0, 160) . '…'
                    );

                    preg_match($linePattern, $line, $match);

                    $this->assertSame(
                        $expectedPayload,
                        $match['payload'],
                        "Payload corrotto alla riga {$lineNumber}"
                    );

                    $key = $match['worker'] . ':' . $match['line'];
                    $this->assertArrayNotHasKey(
                        $key,
                        $seen,
                        "Riga duplicata worker={$match['worker']} line={$match['line']}"
                    );
                    $seen[$key] = true;
                }
            } finally {
                fclose($handle);
            }

            $this->assertSame(
                $expectedLines,
                $lineNumber,
                "Numero di righe atteso {$expectedLines}, trovate {$lineNumber}: "
                . 'scritture perse o interlacciate'
            );

            $workersSeen = array_unique(array_map(
                static fn (string $k): string => explode(':', $k, 2)[0],
                array_keys($seen)
            ));

            $this->assertCount(
                self::WORKERS,
                $workersSeen,
                'Non tutti i worker hanno scritto'
            );
        }

        /**
         * Canary statico: se qualcuno rimuove un lock esplicito dal Logger,
         * la CI deve diventare rossa indipendentemente dal comportamento
         * del filesystem.
         */
        public function testLoggerSourceUsesAnExplicitLock(): void
        {
            $loggerPath = __DIR__ . '/../../src/includes/logger.php';
            $this->assertFileExists($loggerPath, 'File logger.php non trovato');

            $source = file_get_contents($loggerPath);
            $this->assertIsString($source);

            $this->assertTrue(
                str_contains($source, 'LOCK_EX') || str_contains($source, 'flock('),
                'Il Logger deve usare LOCK_EX o flock() per scritture concorrenti sicure'
            );
        }

        private static function workerScript(): string
        {
            return <<<'PHP'
    <?php

    declare(strict_types=1);

    $root = dirname(__DIR__);

    require_once $root . '/src/includes/enums.php';
    require_once $root . '/src/includes/security.php';
    require_once $root . '/src/includes/logger.php';

    if ($argc < 5) {
        fwrite(STDERR, "Uso: logger_worker.php <logDir> <workerId> <lineCount> <payloadBytes>\n");
        exit(2);
    }

    $logDir       = $argv[1];
    $workerId     = (int) $argv[2];
    $lineCount    = (int) $argv[3];
    $payloadBytes = (int) $argv[4];

    $logger = new \Zexa\Includes\Logger($logDir);
    $payload = str_repeat('x', $payloadBytes);

    // Simula un contesto HTTP, altrimenti Logger::log() fa fallback su php_sapi_name()
    $_SERVER['REQUEST_METHOD'] = 'GET';

    for ($i = 0; $i < $lineCount; $i++) {
        try {
            $logger->log(
                'test',
                \Zexa\Includes\log_priority::info,
                sprintf('worker=%d line=%d payload=%s', $workerId, $i, $payload)
            );
        } catch (\Throwable $e) {
            fwrite(
                STDERR,
                sprintf(
                    "Errore worker=%d line=%d: %s\n%s\n",
                    $workerId,
                    $i,
                    $e->getMessage(),
                    $e->getTraceAsString()
                )
            );
            exit(1);
        }
    }

    exit(0);
    PHP;
        }
    }