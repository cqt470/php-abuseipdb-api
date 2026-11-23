<?php
    declare(strict_types=1);

    namespace Zexa\Tests\Findings;

    require_once __DIR__.'/ServerHelper.php';

    use PHPUnit\Framework\TestCase;

    /**
     * Black-box tests on the cron script src/cron/flush.php, run from the command line
     * against a mock Discord webhook and the mock AbuseIPDB upstream.
     */
    final class FlushFindingsTest extends TestCase
    {
        private const DISCORD      = 17501; // accepts every message
        private const DISCORD_DOWN = 17502; // answers 500
        private const WEB          = 17503; // flush.php served over HTTP: must refuse to run
        private const UPSTREAM     = 17504;

        private static string $discord_log;
        private static string $discord_down_log;
        private static string $upstream_hits;

        private string $dir;

        public static function setUpBeforeClass(): void
        {
            $tmp = ServerHelper::tmp();
            self::$discord_log = "$tmp/discord.log";
            self::$discord_down_log = "$tmp/discord-down.log";
            self::$upstream_hits = "$tmp/flush-upstream-hits.log";

            try {
                ServerHelper::start(self::DISCORD, __DIR__.'/fixtures/mock-discord-router.php', ['FINDINGS_DISCORD_FILE' => self::$discord_log]);
                ServerHelper::start(self::DISCORD_DOWN, __DIR__.'/fixtures/mock-discord-router.php', ['FINDINGS_DISCORD_FILE' => self::$discord_down_log, 'FINDINGS_DISCORD_STATUS' => '500']);
                ServerHelper::start(self::UPSTREAM, __DIR__.'/fixtures/mock-upstream-router.php', ['FINDINGS_HITS_FILE' => self::$upstream_hits]);

                $web = "$tmp/flush-web";
                if (!is_dir("$web/logs")) mkdir("$web/logs", 0777, true);
                if (!is_dir("$web/queue")) mkdir("$web/queue", 0777, true);
                ServerHelper::start(self::WEB, __DIR__.'/fixtures/flush-runner.php', ['FINDINGS_TMP' => $web]);
            } catch (\Throwable $e) {
                ServerHelper::stop_all();
                throw $e;
            }
        }

        public static function tearDownAfterClass(): void
        {
            ServerHelper::stop_all();
        }

        protected function setUp(): void
        {
            file_put_contents(self::$discord_log, '');
            file_put_contents(self::$discord_down_log, '');
            file_put_contents(self::$upstream_hits, '');

            $this->dir = ServerHelper::tmp().'/flush/'.$this->name();
            foreach (['logs', 'queue'] as $sub) {
                if (!is_dir("$this->dir/$sub")) mkdir("$this->dir/$sub", 0777, true);
            }
            foreach (glob("$this->dir/queue/*") ?: [] as $f) unlink($f);
            file_put_contents("$this->dir/ips.json", '[]');
        }

        /** @return array{code:int, out:string} */
        private function flush(array $env = []): array
        {
            $out = "$this->dir/stdout.txt";
            $proc = proc_open(
                [PHP_BINARY, __DIR__.'/fixtures/flush-runner.php'],
                [1 => ['file', $out, 'w'], 2 => ['file', "$this->dir/stderr.txt", 'w']],
                $pipes, null,
                array_merge(getenv(), [
                    'FINDINGS_TMP' => $this->dir,
                    'FINDINGS_DISCORD_URL' => 'http://127.0.0.1:'.self::DISCORD.'/',
                    'FINDINGS_BASE_URL' => 'http://127.0.0.1:'.self::UPSTREAM,
                ], $env)
            );
            $code = proc_close($proc);

            return ['code' => $code, 'out' => (string)file_get_contents($out)];
        }

        private function queue(string $name, ?array $items = null): array
        {
            $file = "$this->dir/queue/$name.json";
            if ($items !== null) file_put_contents($file, json_encode($items));
            return file_exists($file) ? (json_decode((string)file_get_contents($file), true) ?? []) : [];
        }

        private static function discord_messages(string $log): array
        {
            $lines = file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            return array_map(fn($l) => json_decode($l, true), $lines);
        }

        private static function notification(string $event, string $ip): array
        {
            return ['event' => $event, 'context' => ['ip' => $ip], 'embed' => ['title' => "single $event", 'description' => $ip, 'color' => '#ff0000'], 'queued_at' => time()];
        }

        // ---------- Discord ----------

        public function test_queued_notifications_are_sent_grouped_in_one_message(): void
        {
            $this->queue('discord', [
                self::notification('failed_auth', '198.51.100.1'),
                self::notification('failed_auth', '198.51.100.1'),
                self::notification('failed_auth', '198.51.100.2'),
                self::notification('cache_added', '198.51.100.3'),
                self::notification('cache_added', '198.51.100.4'),
            ]);

            $r = $this->flush();

            $this->assertSame(0, $r['code'], $r['out']);
            $messages = self::discord_messages(self::$discord_log);
            $this->assertCount(1, $messages);
            $this->assertSame(
                ['3 failed authentications from 2 IPs', '2 IPs added to the cache'],
                array_column($messages[0]['embeds'], 'title')
            );
            $this->assertSame([], $this->queue('discord'), 'the queue must be empty after a successful flush');
        }

        public function test_notifications_stay_queued_when_discord_is_down(): void
        {
            $items = [self::notification('failed_auth', '198.51.100.1'), self::notification('generic', '198.51.100.9')];
            $this->queue('discord', $items);

            $this->flush(['FINDINGS_DISCORD_URL' => 'http://127.0.0.1:'.self::DISCORD_DOWN.'/']);

            $this->assertCount(1, self::discord_messages(self::$discord_down_log), 'Discord was not called');
            $this->assertCount(2, $this->queue('discord'), 'undelivered notifications were lost');
        }

        public function test_at_most_discord_max_per_flush_messages_are_sent(): void
        {
            // 12 generic notifications = 2 messages (10 + 2 embeds); only 1 allowed per flush
            $this->queue('discord', array_map(fn($i) => self::notification('generic', "198.51.100.$i"), range(1, 12)));

            $this->flush(['FINDINGS_DISCORD_MAX' => '1']);

            $this->assertCount(1, self::discord_messages(self::$discord_log));
            $this->assertCount(2, $this->queue('discord'), 'the notifications that did not fit must wait for the next flush');
        }

        public function test_empty_queues_send_nothing(): void
        {
            $r = $this->flush();

            $this->assertSame(0, $r['code'], $r['out']);
            $this->assertSame([], self::discord_messages(self::$discord_log));
        }

        // ---------- reports ----------

        private static function report(string $ip, array $categories = [18]): array
        {
            return ['ip' => $ip, 'categories' => $categories, 'comment' => 'test', 'timestamp' => '2026-11-23T07:55:19Z', 'queued_at' => time()];
        }

        private static function reported_ips(): array
        {
            $ips = [];
            foreach (file(self::$upstream_hits, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                if (str_starts_with($line, 'POST /report')) $ips[] = $line;
            }
            return $ips;
        }

        public function test_queued_reports_are_sent_retried_or_discarded(): void
        {
            $this->queue('reports', [
                self::report('198.51.100.10', [22]),
                self::report('203.0.113.97'),  // 429 "once in 15 minutes": temporary
                self::report('203.0.113.98'),  // 422: permanent
                self::report('198.51.100.11'),
            ]);

            $r = $this->flush();

            $this->assertSame(0, $r['code'], $r['out']);
            $this->assertSame(['sent' => 2, 'retry' => 1, 'discarded' => 1], json_decode($r['out'], true)['reports'] ?? null, $r['out']);
            $this->assertSame(['203.0.113.97'], array_column($this->queue('reports'), 'ip'), 'only the temporary failure stays queued');

            $embeds = self::discord_messages(self::$discord_log)[0]['embeds'] ?? [];
            $this->assertSame('Reports: 2 sent, 1 kept for retry, 1 discarded', $embeds[0]['title'] ?? null);
        }

        public function test_daily_limit_stops_the_flush_and_keeps_every_report(): void
        {
            $this->queue('reports', [self::report('203.0.113.99'), self::report('198.51.100.12')]);

            $this->flush();

            $this->assertCount(1, self::reported_ips(), 'after the daily limit no other report must be tried');
            $this->assertSame(['203.0.113.99', '198.51.100.12'], array_column($this->queue('reports'), 'ip'));
        }

        public function test_at_most_reports_max_per_flush_reports_are_sent(): void
        {
            $this->queue('reports', array_map(fn($i) => self::report("198.51.100.$i"), range(20, 24)));

            $r = $this->flush(['FINDINGS_REPORTS_MAX' => '2']);

            $this->assertCount(2, self::reported_ips(), $r['out']);
            $this->assertCount(3, $this->queue('reports'));
        }

        // ---------- the script itself ----------

        public function test_flush_refuses_to_run_over_http(): void
        {
            $r = ServerHelper::request(self::WEB, '', [], 'GET');
            $this->assertSame(403, $r['status'], $r['body']);
        }

        public function test_two_flushes_never_overlap(): void
        {
            $lock = fopen("$this->dir/queue/flush.lock", 'c');
            flock($lock, LOCK_EX);
            $this->queue('discord', [self::notification('generic', '198.51.100.1')]);

            try {
                $r = $this->flush();
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }

            $this->assertSame(0, $r['code']);
            $this->assertStringContainsString('Another flush is running', $r['out']);
            $this->assertCount(1, $this->queue('discord'), 'the second flush must not touch the queue');
        }
    }
