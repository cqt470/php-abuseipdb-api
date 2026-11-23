# Include aggregator

[`src/includes/index.php`](../../../src/includes/index.php) loads the shared
include files in this order:

1. `handler_errors.php`
2. `enums.php`
3. `logger.php`
4. `security.php`
5. `cache.php`
6. `report_summary.php`
7. `queue.php`
8. `flusher.php`
9. `endpoint.php`
10. `webhook.php`

Use it from an endpoint with:

```php
include_once __DIR__ . "/../includes/index.php";
```

The list is static and must be updated when a new shared include is added.
