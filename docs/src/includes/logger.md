# logger.php

Usage documentation for [`src/includes/logger.php`](../../../src/includes/logger.php).

## Purpose

`Logger` writes timestamped, categorized log rows to plain-text files inside the `logs/` directory at the repository root. It is the only logging facility in the project: there is no dependency on external logging libraries.

## Inclusion

```php
include_once __DIR__ . "/index.php";
```

In practice the file is loaded through the shared aggregator:

```php
include_once __DIR__ . "/../includes/index.php";
```

## Exposed class and public methods

- Class: `Zexa\Includes\Logger` (no namespace)
- Public methods:
  - `__construct(string $folderpath)`
  - `log(string $category, log_priority $priority, string $text, bool $include_ip = false)`

## `__construct()`

Resolves the target folder with `realpath()` and throws `Exception("Folder doesn't exist: ...")` when the path is missing or not a directory. The instance stores the resolved absolute path.

Known caveat: `logs/` is gitignored and therefore absent in a fresh clone, so the directory must be created before the first request (`mkdir -p logs`), otherwise every endpoint construction fails.

## `log()`

Appends one row to `<folderpath>/<category>.log`, creating the file on first write:

```
[LEVEL] ISO-8601-timestamp - text (IP a.b.c.d)
```

- `$category` selects the destination file (for example `auth` writes to `logs/auth.log`).
- `$priority` is a `Zexa\Includes\log_priority` enum case; its value is uppercased in the row prefix (currently `DEBUG` or `INFO`).
- `$include_ip` appends the client IP resolved by `Security::get_ip()` in parentheses when `true`.

The method has no declared return type and returns `null` in practice.

## Current usage in the codebase

The single call site is `AbuseIPDBEndpoint` in [`src/api/abuseipdb.php`](../../../src/api/abuseipdb.php), which logs failed token authentication attempts to `logs/auth.log` at `debug` priority with the client IP included. The endpoint instance is available to subclasses through the protected `$logger` property of `Endpoint`.

## Minimal usage example

```php
$logger = new Logger(__DIR__ . "/../../logs/");
$logger->log("auth", log_priority::debug, "Authentication failed.", true);
```

## Internal dependencies

- [`src/includes/index.php`](./index.md)
- [`src/includes/enums.php`](./enums.md) (`log_priority`)
- [`src/includes/security.php`](./security.md) (`Security::get_ip()` when `$include_ip` is `true`)

## Known limitations and TODOs

- A dead assignment (`$ip = "Security::get_ip()"`) exists at the top of `log()` and has no effect; it is scheduled for cleanup.
- There is no log rotation or level filtering: every call appends unconditionally.
- Only two priorities (`debug`, `info`) exist in `log_priority` today.
