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

`add_entry()` ignores payloads without `data.ipAddress`, avoids duplicate
addresses, adds `added_at` to the upstream `data` object, and rewrites the
cache file. A new entry produces a green `New IP added to cache` notification.

`get_entry()` returns the first matching entry or `null`. `delete_entry()`
rewrites the file without matching entries.

## Storage and limitations

The default file is `CACHE_FILE` (`cache/ips.json`). Values are currently
typed as strings by the lookup methods. Cache reads and rewrites do not use
file locking, so deployments with concurrent writers should provide suitable
filesystem coordination.
