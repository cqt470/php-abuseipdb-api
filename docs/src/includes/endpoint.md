# endpoint.php

Reference for [`src/includes/endpoint.php`](../../../src/includes/endpoint.php).

`Endpoint` is the shared base class for JSON endpoints.

## Constructor

```php
public function __construct(
    string $endpoint_name = "generic",
    bool $is_post_needed = true,
    bool $is_body_needed = true
)
```

By default, constructor behavior is:

- apply CORS headers via `handle_cors()`
- require HTTP `POST`
- require a non-empty JSON object body

## Protected methods for child endpoints

- `register_action(string $action_name, callable $handler, bool $requires_csrf = true, bool $requires_auth = false): void`
- `get_token(): ?string`
- `handle_cors(): void`
- `route_action(string $action_param_name = "action"): void`
- `get_parameter(string $name, string $type, bool $optional = false): mixed`
- `get_parameters(array $parameters): array`
- `get_request_data(): array`
- `authenticate_user(bool $force_auth = true): ?array`
- `validate_csrf(string $csrf_token): bool`
- `send_success(string|array|null $message, int $http_code = 200): never`
- `send_error(string|array|null $message, int $http_code = 400): never`
- `add_response_parameter(string $key, mixed $value): void`

## Type validation

Supported `get_parameter(s)` types:

- `int`
- `bool`
- `float`
- `string`
- `array`

If a required field is missing or has the wrong type, `send_error()` is used.

## Response envelope

All responses are emitted by `send_json()` with this structure:

```json
{
  "message": "...",
  "success": true,
  "parameters": {}
}
```

`parameters` is included only when at least one response parameter is set.

## CORS behavior

CORS headers are sourced from configuration constants:

- `CORS_ALLOW_ORIGIN`
- `CORS_ALLOW_HEADERS`

`OPTIONS` requests return `204` and terminate early.
