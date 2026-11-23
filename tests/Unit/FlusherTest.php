<?php
    declare(strict_types=1);

    namespace Zexa\Tests\Unit;

    use PHPUnit\Framework\TestCase;
    use Zexa\Includes\Flusher;

    final class FlusherTest extends TestCase
    {
        private static function item(string $event, ?string $ip = null, string $title = 't'): array
        {
            return ['event' => $event, 'context' => $ip === null ? [] : ['ip' => $ip], 'embed' => ['title' => $title, 'description' => 'd']];
        }

        public function test_failed_authentications_are_grouped_by_ip(): void
        {
            $groups = Flusher::group([
                self::item('failed_auth', '198.51.100.1'),
                self::item('failed_auth', '198.51.100.2'),
                self::item('failed_auth', '198.51.100.1'),
                self::item('failed_auth', '198.51.100.1'),
            ]);

            $this->assertCount(1, $groups);
            $this->assertSame('4 failed authentications from 2 IPs', $groups[0]['embed']['title']);
            $this->assertSame("`198.51.100.1` × 3\n`198.51.100.2`", $groups[0]['embed']['description']);
            $this->assertCount(4, $groups[0]['items']);
        }

        public function test_each_event_gets_its_own_group_and_generic_ones_stay_separate(): void
        {
            $groups = Flusher::group([
                self::item('cache_added', '198.51.100.1'),
                self::item('generic', null, 'first'),
                self::item('cache_added', '198.51.100.2'),
                self::item('cache_expired', '198.51.100.3', 'expired'),
                self::item('nonsense', null, 'second'),
            ]);

            $this->assertSame(
                ['2 IPs added to the cache', 'expired', 'first', 'second'],
                array_map(fn($g) => $g['embed']['title'], $groups)
            );
        }

        public function test_malformed_items_are_skipped(): void
        {
            $this->assertSame([], Flusher::group(['garbage', ['event' => 'generic'], ['embed' => 'x']]));
        }

        public function test_describe_stays_within_the_limit(): void
        {
            $lines = array_map(fn($i) => "`198.51.100.$i`", range(1, 200));

            $text = Flusher::describe($lines, 100);

            $this->assertLessThanOrEqual(100, mb_strlen($text));
            $this->assertMatchesRegularExpression('/… and \d+ more$/', $text);

            $shown = count(explode("\n", $text)) - 1;
            preg_match('/and (\d+) more$/', $text, $m);
            $this->assertSame(200, $shown + (int)$m[1], 'every line is either shown or counted');
        }

        public function test_describe_keeps_everything_when_it_fits(): void
        {
            $this->assertSame("a\nb\nc", Flusher::describe(['a', 'b', 'c'], 100));
        }

        public function test_chunk_respects_embed_count_and_message_size(): void
        {
            $small = fn() => ['embed' => ['title' => 't', 'description' => 'd'], 'items' => []];
            $this->assertSame([10, 2], array_map('count', Flusher::chunk(array_map(fn() => $small(), range(1, 12)))));

            $big = fn() => ['embed' => ['title' => 't', 'description' => str_repeat('x', 4000)], 'items' => []];
            $this->assertSame([1, 1, 1], array_map('count', Flusher::chunk([$big(), $big(), $big()])), 'two 4000-char embeds exceed 6000 characters');
        }
    }
