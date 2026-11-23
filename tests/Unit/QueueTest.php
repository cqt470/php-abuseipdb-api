<?php
    declare(strict_types=1);

    namespace Zexa\Tests\Unit;

    use PHPUnit\Framework\TestCase;
    use Zexa\Includes\Queue;

    final class QueueTest extends TestCase
    {
        private string $file;

        protected function setUp(): void
        {
            $this->file = \QUEUE_DIR.'unit-test.json';
            if (file_exists($this->file)) unlink($this->file);
        }

        protected function tearDown(): void
        {
            if (file_exists($this->file)) unlink($this->file);
        }

        private function items(): array
        {
            return json_decode((string) file_get_contents($this->file), true);
        }

        public function test_the_file_is_created_as_an_empty_list(): void
        {
            new Queue($this->file);
            $this->assertSame([], $this->items());
        }

        public function test_push_appends_with_queued_at(): void
        {
            $queue = new Queue($this->file);
            $queue->push(['n' => 1]);
            $queue->push(['n' => 2]);

            $items = $this->items();
            $this->assertSame([1, 2], array_column($items, 'n'));
            $this->assertIsInt($items[0]['queued_at']);
            $this->assertSame(2, $queue->count());
        }

        public function test_drain_removes_and_returns_the_oldest_items(): void
        {
            $queue = new Queue($this->file);
            foreach ([1, 2, 3] as $n) $queue->push(['n' => $n]);

            $this->assertSame([1, 2], array_column($queue->drain(2), 'n'));
            $this->assertSame([3], array_column($this->items(), 'n'));
            $this->assertSame([3], array_column($queue->drain(), 'n'));
            $this->assertSame([], $queue->drain());
        }

        public function test_requeue_puts_items_back_at_the_head(): void
        {
            $queue = new Queue($this->file);
            foreach ([1, 2] as $n) $queue->push(['n' => $n]);
            $taken = $queue->drain(1);
            $queue->push(['n' => 3]);

            $queue->requeue($taken);

            $this->assertSame([1, 2, 3], array_column($this->items(), 'n'));
        }

        public function test_push_or_merge_merges_items_with_the_same_key(): void
        {
            $queue = new Queue($this->file);
            $merge = fn(array $old, array $new) => ['ip' => $old['ip'], 'tags' => array_merge($old['tags'], $new['tags'])];

            $queue->push_or_merge('ip', ['ip' => '198.51.100.1', 'tags' => ['a']], $merge);
            $queue->push_or_merge('ip', ['ip' => '198.51.100.2', 'tags' => ['x']], $merge);
            $queue->push_or_merge('ip', ['ip' => '198.51.100.1', 'tags' => ['b']], $merge);

            $items = $this->items();
            $this->assertCount(2, $items);
            $this->assertSame(['a', 'b'], $items[0]['tags']);
            $this->assertArrayHasKey('queued_at', $items[0]);
        }

        public function test_corrupted_file_is_never_overwritten(): void
        {
            file_put_contents($this->file, '{not json');
            $queue = new Queue($this->file);

            $this->assertFalse($queue->push(['n' => 1]));
            $this->assertSame([], $queue->drain());
            $this->assertSame('{not json', file_get_contents($this->file));
        }

        public function test_enabled_follows_the_constant(): void
        {
            $this->assertSame(\ENABLE_QUEUE, Queue::enabled());
        }
    }
