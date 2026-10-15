# index.php

Usage documentation for [`src/includes/index.php`](../../../src/includes/index.php).

## Purpose

`index.php` is an include aggregator that loads all core include files in a single entry point.

## Inclusion

```php
include_once __DIR__ . "/../../../src/includes/index.php";
```

## Exposed behavior

- Includes these files:
  - [`src/includes/enums.php`](../../../src/includes/enums.php)
  - [`src/includes/security.php`](../../../src/includes/security.php)
  - [`src/includes/cache.php`](../../../src/includes/cache.php)
  - [`src/includes/endpoint.php`](../../../src/includes/endpoint.php)
  - [`src/includes/webhook.php`](../../../src/includes/webhook.php)

## Minimal usage example

```php
include_once __DIR__ . "/../../../src/includes/index.php";
```

## Internal dependencies

- This file directly depends on all include files listed above.

## Known limitations and TODOs

- No explicit TODO in this file.
- The include list is static and manually maintained.
