# Queue

[`src/includes/queue.php`](../../../src/includes/queue.php) is a FIFO queue
stored in a JSON file (a list of items). It uses the same locking scheme as
[`Cache`](./cache.md): every change is a read-modify-write cycle under an
exclusive `flock()`, and a corrupted file is never overwritten (a `CRITICAL`
row is written to `queue.log`).

## API

```php
__construct(string $filepath)           // creates the file as [] if missing
Queue::enabled(): bool                   // ENABLE_QUEUE, false if not defined
Queue::open(string $name): Queue         // QUEUE_DIR/<name>.json
push(array $item): bool
push_or_merge(string $key, array $item, callable $merge): bool
drain(?int $limit = null): array
requeue(array $items): bool
count(): int
```

Every item gets a `queued_at` timestamp. `push_or_merge()` looks for a queued
item with the same value for `$key` and replaces it with
`$merge($queued, $item)`, keeping its position and `queued_at`; otherwise it
appends the item. `drain()` removes and returns the oldest items in one
atomic step; `requeue()` puts items back at the head of the queue, before
anything queued in the meantime. The write methods return `false` when the
file is corrupted.

The named queues are `Queue::DISCORD` (`discord.json`, written by
[`Webhook::use_discord()`](./webhook.md)) and `Queue::REPORTS`
(`reports.json`, written by the `report` action). Both are flushed by the
cron script described in [`Flusher`](./flusher.md).
