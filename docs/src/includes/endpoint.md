# endpoint.php

Usage documentation for [`src/includes/endpoint.php`](../../../src/includes/endpoint.php).

`Endpoint` is a standalone base class for JSON API endpoint handling. It does not require a database, global configuration, or legacy `*-old.php` files.

## Inclusion

Include the class explicitly in endpoint files:

```php
require_once __DIR__ . "/../../../src/includes/endpoint.php";
```

## Minimal endpoint

A concrete endpoint extends `Endpoint`, registers one or more actions, and starts routing. By default, these checks are enabled:

- HTTP method must be `POST`.
- JSON body must not be empty.
- CSRF token is required for each registered action unless disabled.

### Request

```http
POST /src/api/status.php
Content-Type: application/json
```

```json
{
  "action": "status",
  "csrf_token": "application-token"
}
```

### Implementation

```php
require_once __DIR__ . "/../../../src/includes/endpoint.php";

final class StatusEndpoint extends Endpoint {
    public function __construct(){
        parent::__construct("status");

        $this->register_action(
            "status",
            fn() => $this->send_success("Service available")
        );

        $this->route_action();
    }
}

new StatusEndpoint();
```

### Response

```json
{
  "message": "Service available",
  "success": true
}
```

## Class methods

Protected methods are designed for subclasses. Private methods are internal details and are not intended for child classes.

### `__construct()`

Initializes endpoint name, verifies HTTP method, and reads JSON body.

```php
public function __construct(
    string $endpoint_name = "generic",
    bool $is_post_needed = true,
    bool $is_body_needed = true
)
```

### `register_action()`

Registers a handler under an action name. You can configure whether CSRF and authentication are required.

```php
protected function register_action(
    string $action_name,
    callable $handler,
    bool $requires_csrf = true,
    bool $requires_auth = false
): void
```

### `route_action()`

Reads the action name from the body, validates registration, executes optional authentication and CSRF checks, and invokes the handler.

```php
protected function route_action(string $action_param_name = "action"): void
```

### `get_parameter()`

Retrieves and validates a single parameter.

```php
protected function get_parameter(
    string $name,
    string $type,
    bool $optional = false
): mixed
```

### `get_parameters()`

Retrieves and validates multiple parameters using a schema. Each value can be a type string or an array with `type` and `optional`.

```php
protected function get_parameters(array $parameters): array
```

### `get_request_data()`

Returns the entire decoded JSON body.

```php
protected function get_request_data(): array
```

### `authenticate_user()`

Hook method to override with application authentication logic. The default implementation returns HTTP `403` when forced.

```php
protected function authenticate_user(bool $force_auth = true): ?array
```

### `validate_csrf()`

Hook method to override with application-specific CSRF validation.

```php
protected function validate_csrf(string $csrf_token): bool
```

### `send_success()`

Sends a success JSON response and terminates execution.

```php
protected function send_success(
    string|array|null $message,
    int $http_code = 200
): never
```

### `send_error()`

Sends an error JSON response and terminates execution.

```php
protected function send_error(
    string|array|null $message,
    int $http_code = 400
): never
```

### `add_response_parameter()`

Adds a value to the `parameters` key in the JSON response.

```php
protected function add_response_parameter(string $key, mixed $value): void
```

### Private methods

`read_request_body()` reads and decodes JSON body. `matches_type()` validates supported types. `send_json()` builds and emits HTTP response.

## Constructor details

```php
parent::__construct(
    string $endpoint_name = "generic",
    bool $is_post_needed = true,
    bool $is_body_needed = true
);
```

- `$endpoint_name` identifies the endpoint.
- `$is_post_needed = false` allows `GET` requests.
- `$is_body_needed = false` allows empty or missing body.

Example for a `GET` endpoint without body:

```php
parent::__construct("health", false, false);
```

The body must be a JSON object. Invalid JSON, a JSON list, or an empty body when required produce HTTP `400`.

## Action registration and routing

```php
$this->register_action(
    string $action_name,
    callable $handler,
    bool $requires_csrf = true,
    bool $requires_auth = false
);
```

Routing reads `action` from request body:

```php
$this->route_action();
```

You can use a different field name:

```php
$this->route_action("operation");
```

In that case, request must contain `operation` instead of `action`. Missing or unregistered action returns HTTP `400`.

### Public action without CSRF

```php
$this->register_action(
    "health",
    fn() => $this->send_success("OK"),
    false
);
```

## Parameters and validation

Supported types are:

- `int`
- `bool`
- `float`
- `string`
- `array`

### Required single parameter

```php
$ip = $this->get_parameter("ip", "string");
```

### Optional single parameter

```php
$verbose = $this->get_parameter("verbose", "bool", true);
```

### Multiple parameters

```php
$values = $this->get_parameters([
    "ip" => "string",
    "verbose" => ["type" => "bool", "optional" => true],
]);
```

Optional missing values are set to `null`. Missing required field, unsupported type, or type mismatch returns HTTP `400`.

Values are not auto-cast. JSON types must already match expected types.

To read full decoded body:

```php
$body = $this->get_request_data();
```

## Responses

### Success response

```php
$this->send_success("IP checked");
```

You can set a custom `2xx` HTTP code:

```php
$this->send_success(["id" => 42], 201);
```

### Error response

```php
$this->send_error("Invalid ip parameter", 422);
```

Responses always follow this structure:

```json
{
  "message": "...",
  "success": true
}
```

### Extra parameters

```php
$this->add_response_parameter("cached", true);
$this->send_success("Result available");
```

Output:

```json
{
  "message": "Result available",
  "success": true,
  "parameters": {
    "cached": true
  }
}
```

`send_success()` and `send_error()` always terminate execution (`never` return type).

## CSRF and authentication

The class does not include an application-specific authentication system.
`validate_csrf()` is an overridable hook:

```php
protected function validate_csrf(string $token): bool
{
    return hash_equals($_SESSION["csrf_token"] ?? "", $token);
}
```

`authenticate_user()` is also a hook. Default behavior sends HTTP `403` when `force_auth` is `true`:

```php
protected function authenticate_user(bool $force_auth = true): ?array
{
    $user = $this->findUserFromToken();

    if ($user === null && $force_auth) {
        $this->send_error("Unauthorized.", 403);
    }

    $this->user = $user;
    return $user;
}
```

Protected action registration example:

```php
$this->register_action(
    "private-status",
    fn() => $this->send_success($this->user),
    true,
    true
);
```

Routing executes authentication and CSRF checks before handler execution.

## Complete example

```php
require_once __DIR__ . "/../../../src/includes/endpoint.php";

final class IpCheckEndpoint extends Endpoint {
    public function __construct(){
        parent::__construct("ip-check");

        $this->register_action(
            "check",
            fn() => $this->checkIp()
        );

        $this->route_action();
    }

    protected function validate_csrf(string $token): bool{
        return hash_equals($_SESSION["csrf_token"] ?? "", $token);
    }

    private function checkIp(): never{
        $ip = $this->get_parameter("ip", "string");
        $this->send_success(["ip" => $ip]);
    }
}

new IpCheckEndpoint();
```
