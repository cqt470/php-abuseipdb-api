# index.php

Reference for [`src/includes/index.php`](../../../src/includes/index.php).

## Purpose

This file is an include aggregator used by endpoint files.

## Included files

- [`src/includes/enums.php`](../../../src/includes/enums.php)
- [`src/includes/security.php`](../../../src/includes/security.php)
- [`src/includes/cache.php`](../../../src/includes/cache.php)
- [`src/includes/endpoint.php`](../../../src/includes/endpoint.php)
- [`src/includes/webhook.php`](../../../src/includes/webhook.php)

## Example

```php
include_once __DIR__ . "/../../../src/includes/index.php";
```

## Notes

The include list is static and must be kept aligned manually when core includes change.
