# Shared enums

[`src/includes/enums.php`](../../../src/includes/enums.php) defines the backed
string enum `Zexa\Includes\log_priority`.

| Case | Value |
| --- | --- |
| `debug` | `debug` |
| `info` | `info` |
| `warning` | `warning` |
| `error` | `error` |
| `critical` | `critical` |

The enum is used by [`Logger`](./logger.md). PHP 8.1 or newer is required.
