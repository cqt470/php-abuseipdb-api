# Cron flush

[`src/cron/flush.php`](../../../src/cron/flush.php) sends what was queued
while `ENABLE_QUEUE` is on. It runs from the command line only (served over
HTTP it answers 403), takes an exclusive non-blocking lock on
`QUEUE_DIR/flush.lock` so two runs never overlap, and prints the statistics
of the run as JSON. Schedule it with cron, see the
[deployment guide](../../DEPLOYMENT.md#queue-and-cron-optional).

The work is done by
[`src/includes/flusher.php`](../../../src/includes/flusher.php):

```php
(new Flusher())->run(): array
(new Flusher())->flush_reports(): array
(new Flusher())->flush_discord(array $extra = []): array
Flusher::merge_reports(array $queued, array $new): array
Flusher::group(array $items): array
Flusher::describe(array $lines, int $limit = 4096): string
Flusher::chunk(array $groups): array
Flusher::reports_embed(array $result): ?array
```

## Reports

`flush_reports()` takes at most `REPORTS_MAX_PER_FLUSH` reports and sends one
`/report` per IP (`Security::report_ip()`), not `/bulk-report`, which the free
plan limits to 5 requests a day.

| AbuseIPDB answer | Outcome |
| --- | --- |
| 2xx | Sent |
| Network error, 5xx, 429 | Kept in the queue for the next run |
| 429 that is not the "same IP once in 15 minutes" rule (daily limit) | Kept, and the remaining reports are not tried in this run |
| Any other 4xx | Discarded and logged |

While queued, reports on the same IP are merged by `merge_reports()`:
categories are joined, different comments concatenated (at most 1024
characters), and the timestamp of the first report is kept.

## Discord

`flush_discord()` drains the `discord` queue and groups the notifications by
event (`group()`): failed authentications, cache additions and expirations
become one embed each, listing the IPs (most frequent first, with `× N`);
other notifications keep their own embed, and a group of one notification
keeps the original embed. `describe()` keeps a description within Discord's
4096 characters, ending with `… and N more`. `chunk()` splits the embeds into
messages of at most 10 embeds and 6000 characters.

At most `DISCORD_MAX_PER_FLUSH` messages are sent per run. When Discord fails,
or the limit is reached, the notifications not delivered go back to the queue.
The summary of the reports (`reports_embed()`, e.g. `Reports: 2 sent, 1 kept
for retry, 1 discarded`) is sent with the same flush.
