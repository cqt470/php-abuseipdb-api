# index.php

Usage documentation for [`src/includes/index.php`](../../src/includes/index.php).

## Purpose

`index.php` is an include aggregator that loads all core include files in a single entry point.

## Inclusion

Endpoint and include files load the aggregator with:

```php
include_once __DIR__ . "/../includes/index.php";
```

## Exposed behavior

- Includes these files, in order:
  - [`src/includes/enums.php`](./enums.md)
  - [`src/includes/logger.php`](./logger.md)
  - [`src/includes/security.php`](./security.md)
  - [`src/includes/cache.php`](./cache.md)
  - [`src/includes/endpoint.php`](./endpoint.md)
  - [`src/includes/webhook.php`](./webhook.md)

## Minimal usage example

```php
include_once __DIR__ . "/../includes/index.php";
```

## Internal dependencies

- This file directly depends on all include files listed above.

## Known limitations and TODOs

- No explicit TODO in this file.
- The include list is static and manually maintained.
