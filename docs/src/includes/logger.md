# Logger

[`src/includes/logger.php`](../../src/includes/logger.php) writes categorized
plain-text rows to a validated directory.

## API

```php
__construct(string $folderpath)
log(
    string $category,
    log_priority $priority,
    string $text,
    bool $include_ip = false
): void
```

The constructor resolves the directory with `realpath()` and throws an
`Exception` when it does not exist or is not a directory. `log()` writes to
`<folderpath>/<category>.log` using `FILE_APPEND | LOCK_EX`.

Rows have this shape:

```text
[INFO] 2026-10-21T19:13:51+00:00 - POST - Message
```

When `$include_ip` is true, the row also contains the IP returned by
`Security::get_ip()`. The priority is converted to uppercase. Log files are
created on first write and are not rotated by the application.

The concurrency regression coverage is in
[`tests/Unit/LoggerConcurrencyTest.php`](../../tests/Unit/LoggerConcurrencyTest.php).
