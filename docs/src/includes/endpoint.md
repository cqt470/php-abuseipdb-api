# endpoint.php

Usage documentation for [`src/includes/endpoint.php`](../../../src/includes/endpoint.php).

`Endpoint` is a standalone base class for JSON API endpoint handling. It does not require a database or global configuration. It centralizes CORS handling, JSON body parsing, parameter validation, action routing, response serialization, and logging initialization for every endpoint that extends it.

> This document replaces the previous Italian-language version of `docs/src/includes/endpoint.md`. All markdown files in this repository are English only.

## Inclusion

Endpoint files include the shared aggregator, which loads `Endpoint` together with all other includes:

```php
include_once __DIR__ . "/../includes/index.php";
```

Alternatively, the class can be included directly:

```php
require_once __DIR__ . "/../../src/includes/endpoint.php";
```

## Responsibilities on construction

The constructor performs, in order:

1. **CORS handling** (`handle_cors()`): sends `Access-Control-Allow-Origin` from `CORS_ALLOW_ORIGIN`, `Access-Control-Allow-Methods: POST, GET, OPTIONS`, and `Access-Control-Allow-Headers` from `CORS_ALLOW_HEADERS`. An `OPTIONS` request is answered with HTTP `204` and terminates execution immediately.
2. **Logger initialization**: creates a `Logger` instance writing to `logs/` at the repository root. The folder must exist, otherwise the constructor throws.
3. **HTTP method check**: when `$is_post_needed` is `true` and the request method is not `POST`, responds HTTP `405` (`"Metodo POST necessario."`).
4. **Body parsing** (`read_request_body()`): decodes the JSON body. Invalid JSON or a missing body (when required) responds HTTP `400`.

```php
public function __construct(
    string $endpoint_name = "generic",
    bool $is_post_needed = true,
    bool $is_body_needed = true
)
```

- `$endpoint_name` identifies the endpoint and is stored in `$endpoint_name`.
- `$is_post_needed = false` allows non-POST request methods.
- `$is_body_needed = false` allows an empty or missing body; parsing failures then yield an empty array instead of an error.

## Minimal endpoint

A concrete endpoint extends `Endpoint`, registers one or more actions, and starts routing.

```php
final class StatusEndpoint extends Endpoint {
    public function __construct(){
        parent::__construct("status");

        $this->register_action(
            "status",
            fn() => $this->send_success("Service available"),
            false
        );

        $this->route_action();
    }
}

new StatusEndpoint();
```

## Action registration and routing

```php
protected function register_action(
    string $action_name,
    callable $handler,
    bool $requires_csrf = true,
    bool $requires_auth = false
): void
```

```php
protected function route_action(string $action_param_name = "action"): void
```

`route_action()` reads the action name from the body (by default the `action` field, overridable), rejects missing or unregistered names with HTTP `400`, runs the authentication and CSRF hooks when the action requires them, and finally invokes the handler.

Note that `requires_csrf` defaults to `true`. Public API actions such as `check` and `report` in `src/api/abuseipdb.php` explicitly register with `requires_csrf = false`, so no CSRF token is needed for them.

## Authentication token (`get_token()`)

Safely extracts the Bearer token from the request headers.

- Signature: `get_token(): ?string`
- **Header extraction**: Checks `getallheaders()` if the function exists. If not (e.g., on CLI or some FPM setups), it safely falls back to `$_SERVER['HTTP_AUTHORIZATION']`.
- **Validation**: Parses the header against the RFC 6750 standard (`Bearer <token>`). 
- **Safety**: If the header is missing, empty, or does not match the Bearer scheme, it returns `null` without emitting PHP warnings or deprecation notices.
- Callers are expected to combine this with a body-token fallback if required by the application logic.

## CORS handling (`handle_cors()`)

```php
protected function handle_cors(): void
```

Sends the CORS headers described above and short-circuits `OPTIONS` preflight requests with HTTP `204`. The origin and allowed headers are configured exclusively through the `CORS_ALLOW_ORIGIN` and `CORS_ALLOW_HEADERS` constants in `env.php`; no endpoint file hardcodes CORS headers anymore.

## Parameter retrieval and validation

```php
protected function get_parameter(
    string $name,
    string $type,
    bool $optional = false
): mixed
```

```php
protected function get_parameters(array $parameters): array
```

Supported types are `int`, `bool`, `float`, `string`, and `array`. Each schema entry can be a plain type string or an array with `type` and `optional` keys:

```php
$values = $this->get_parameters([
    "ip" => "string",
    "timestamp" => ["type" => "string", "optional" => true],
]);
```

Optional missing values are returned as `null`. A missing required field, an unsupported type, or a type mismatch responds HTTP `400`. Values are never auto-cast: the JSON type must already match the expected type. `matches_type()` accepts integers where `float` is expected.

The full decoded body is available through:

```php
protected function get_request_data(): array
```

## Responses

Every response is serialized by `send_json()` and follows this envelope:

```json
{
  "message": "string or null",
  "success": true
}
```

```php
protected function send_success(
    string|array|null $message,
    int $http_code = 200
): never
```

```php
protected function send_error(
    string|array|null $message,
    int $http_code = 400
): never
```

`success` is `true` exactly when `$http_code` is in the `2xx` range. Both methods terminate execution and have return type `never`.

Additional data is attached through:

```php
protected function add_response_parameter(string $key, mixed $value): void
```

When at least one response parameter has been added, the envelope gains a `parameters` key:

```json
{
  "message": "Result available",
  "success": true,
  "parameters": {
    "cached": true
  }
}
```

If `json_encode()` fails, `send_json()` responds HTTP `500` with a hardcoded fallback body (`"internal server error! :("`).

## CSRF and authentication hooks

The class ships no application-specific authentication. Both behaviors are overridable hooks:

```php
protected function validate_csrf(string $csrf_token): bool
```

The default implementation only checks that the token is a non-empty string. An invalid CSRF token responds HTTP `400` (`"Token CSRF non valido."`).

```php
protected function authenticate_user(bool $force_auth = true): ?array
```

The default implementation responds HTTP `403` (`"Non autorizzato."`) when `$force_auth` is `true`, and returns `null` otherwise. Actions registered with `requires_auth = true` run this hook before their handler.

## Logging

The constructor instantiates `Logger` (writing to `logs/`) and stores it in the protected `$logger` property. Subclasses are expected to log relevant events through:

```php
$this->logger->log("auth", log_priority::debug, "Authentication to /abuseipdb API failed via token.", true);
```

See [`logger.md`](./logger.md) and [`enums.md`](./enums.md) for the logging API and the available priorities.

## Internal methods

`read_request_body()`, `matches_type()`, and `send_json()` are private implementation details and are not accessible to child classes.

## Complete example

```php
include_once __DIR__ . "/../includes/index.php";

final class IpCheckEndpoint extends Endpoint {
    public function __construct(){
        parent::__construct("ip-check");

        $this->register_action(
            "check",
            fn() => $this->checkIp(),
            false
        );

        $this->route_action();
    }

    private function checkIp(): never{
        $ip = $this->get_parameter("ip", "string");
        $this->send_success(["ip" => $ip]);
    }
}

new IpCheckEndpoint();
```

## Known limitations and TODOs

- `get_token()` emits warnings when the `Authorization` header is missing and depends on `getallheaders()` availability (see above).
- `validate_csrf()` default implementation does not perform timing-safe comparison.
