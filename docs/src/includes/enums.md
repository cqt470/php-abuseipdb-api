# enums.php

Usage documentation for [`src/includes/enums.php`](../../../src/includes/enums.php).

## Purpose

`enums.php` defines shared enums used by include classes.

## Inclusion

```php
include_once __DIR__ . "/../../../src/includes/enums.php";
```

## Exposed enum

- Enum: `Zexa\Includes\frequency_ip_search`
- Cases:
  - `Fallback`
  - `Always`

## Minimal usage example

```php
use Zexa\Includes\frequency_ip_search;

$mode = frequency_ip_search::Fallback;
```

## Internal dependencies

- No internal file dependencies.

## Known limitations and TODOs

- No known TODO in this file.
- Project minimum PHP is 8.1 because enums are required.
