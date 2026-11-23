<?php
    declare(strict_types=1);

    namespace Zexa\Tests\Findings;

    /**
     * Starts PHP built-in servers without a shell wrapper (array form of
     * proc_open), so proc_terminate really kills them on Windows too.
     */
    final class ServerHelper
    {
        /** @var array<int, resource> */
        private static array $procs = [];

        public static function start(int $port, string $router, array $env = []): void
        {
            if (self::probe($port)) {
                throw new \RuntimeException("Port $port already in use, stop the process holding it.");
            }

            $log = self::tmp().'/server-'.$port.'.log';
            $descriptors = [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']];
            $proc = proc_open(
                [PHP_BINARY, '-S', "127.0.0.1:$port", $router],
                $descriptors, $pipes, null, array_merge(getenv(), $env)
            );
            if (!is_resource($proc)) throw new \RuntimeException("Cannot start server on $port");
            self::$procs[$port] = $proc;

            $deadline = microtime(true) + 5.0;
            while (microtime(true) < $deadline) {
                if (self::probe($port)) return;
                usleep(100_000);
            }
            throw new \RuntimeException("Server on $port did not start, see $log");
        }

        public static function stop_all(): void
        {
            foreach (self::$procs as $proc) {
                proc_terminate($proc);
                proc_close($proc);
            }
            self::$procs = [];
        }

        public static function probe(int $port): bool
        {
            // the project's error handler ignores "@", so mute warnings explicitly
            set_error_handler(static fn() => true);
            try {
                $fp = fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            } finally {
                restore_error_handler();
            }
            if ($fp) { fclose($fp); return true; }
            return false;
        }

        public static function tmp(): string
        {
            $dir = dirname(__DIR__).'/tmp/findings';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            return $dir;
        }

        /** @return array{status:int, body:string, json:mixed} */
        public static function request(int $port, mixed $body, array $headers = [], string $method = 'POST'): array
        {
            $content = is_string($body) ? $body : json_encode($body);
            $h = "Content-Type: application/json\r\n";
            foreach ($headers as $k => $v) $h .= "$k: $v\r\n";

            $ctx = stream_context_create(['http' => [
                'method' => $method, 'header' => $h, 'content' => $content,
                'ignore_errors' => true, 'timeout' => 40,
            ]]);
            $resp = file_get_contents("http://127.0.0.1:$port/", false, $ctx);
            preg_match('{HTTP/\S+\s(\d{3})}', $http_response_header[0] ?? '', $m);

            return ['status' => (int)($m[1] ?? 0), 'body' => (string)$resp, 'json' => json_decode((string)$resp, true)];
        }
    }
