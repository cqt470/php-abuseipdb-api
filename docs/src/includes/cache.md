# Cache

[`src/includes/cache.php`](../../../src/includes/cache.php) implements a JSON
file cache. The constructor requires the target file to exist and throws an
`Exception` otherwise.

## API

```php
__construct(string $filepath)
check_for_entry(string $key, string $value): bool
add_entry(array $ip): void
get_entry(string $key, string $value): ?array
delete_entry(string $key, string $value): void
```

`check_for_entry()` scans the decoded JSON array. Entries whose `added_at` is
older than `time() - CACHE_TTL` are deleted and produce a `TTL Reached`
Discord notification. The scan continues after eviction, so later valid
entries are still considered.

`add_entry()` ignores payloads without `data.ipAddress`, replaces an existing
entry for the same address (refresh), adds `added_at` to the upstream `data`
object, and rewrites the cache file. A new entry produces a green `New IP added
to cache` notification; a refresh does not.

`get_entry()` returns the first matching entry or `null`. `delete_entry()`
rewrites the file without matching entries.

If the cache file contains invalid JSON, lookups behave as an empty cache and
writes are skipped, so the existing content is never overwritten. A `CRITICAL`
row is written to `cache.log`; fix or reset the file to `[]` manually.

## Storage and limitations

The default file is `CACHE_FILE` (`cache/ips.json`). Values are currently
typed as strings by the lookup methods. Every read-modify-write cycle runs
under an exclusive `flock()` and reads use a shared lock, so concurrent
requests cannot overwrite each other's entries. Discord notifications are sent
after the lock is released.
