# enums.php

Usage documentation for [`src/includes/enums.php`](../../src/includes/enums.php).

## Purpose

`enums.php` defines the shared enums used by include classes.

## Inclusion

```php
include_once __DIR__ . "/index.php";
```

The file is loaded first by the shared aggregator:

```php
include_once __DIR__ . "/../includes/index.php";
```

## Exposed enum

- Enum: `Zexa\Includes\log_priority`
- Backing type: `string`
- Cases:
  - `debug` = `"debug"`
  - `info` = `"info"`

`log_priority` classifies log rows written by `Logger`. Currently only these two levels exist; `debug` is used for diagnostics such as failed authentication attempts, and `info` is reserved for notable runtime events. Additional levels (warning, error, critical) are a natural extension point when logging coverage expands.

## Minimal usage example

```php
use Zexa\Includes\log_priority;

$logger->log("auth", log_priority::debug, "Authentication failed.", true);
```

## Internal dependencies

- No internal file dependencies.

## Known limitations and TODOs

- No known TODO in this file.
- Project minimum PHP is 8.1 because enums are required.
