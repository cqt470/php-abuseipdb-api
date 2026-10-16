# security.php

Usage documentation for [`src/includes/security.php`](../../../src/includes/security.php).

## Purpose

`Security` provides helper methods for IP retrieval, IP validation, AbuseIPDB check calls, and cache integration.

## Inclusion

```php
include_once __DIR__ . "/../../../src/includes/security.php";
```

## Exposed class and public methods

- Class: `Zexa\Includes\Security`
- Public static methods:
  - `research_ip(): string`
  - `get_ip(frequency_ip_search $research = frequency_ip_search::Fallback): string`
  - `get_ip_info(?string $ip = null, bool $skip_cache = false): ?array`
  - `validate_ip(string $ip): bool`

## Minimal usage example

```php
use Zexa\Includes\Security;

$info = Security::get_ip_info("8.8.8.8");
$isValid = Security::validate_ip("8.8.8.8");
```

## Internal dependencies

- [`src/includes/index.php`](../../../src/includes/index.php)
- [`src/includes/cache.php`](../../../src/includes/cache.php)
- [`src/includes/enums.php`](../../../src/includes/enums.php)
- [`env.php`](../../../env.php)

## Known limitations and TODOs

- TODO in source: improve robustness of client IP source checks.
- TODO in source: potential string injection concern in enum error path.
- Cache persistence has no TTL expiration logic.
