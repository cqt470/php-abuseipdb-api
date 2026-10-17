# endpoint.php

Reference for [`src/includes/endpoint.php`](../../src/includes/endpoint.php).

`Endpoint` is the base class used by API endpoints for request validation, action routing, and JSON responses.

## Constructor

```php
public function __construct(
    string $endpoint_name = "generic",
    bool $is_post_needed = true,
    bool $is_body_needed = true
)
```

The constructor applies CORS headers, validates HTTP method, and parses the JSON body.

## Main extension points

- `register_action(...)` to map action names to handlers.
- `route_action(...)` to resolve and execute an action.
- `validate_csrf(...)` to customize CSRF checks.
- `authenticate_user(...)` to customize auth checks.

## Parameter validation helpers

- `get_parameter(...)` for one field.
- `get_parameters(...)` for schema-based validation.

Supported types are `int`, `bool`, `float`, `string`, and `array`.

## Response helpers

- `send_success(...)`
- `send_error(...)`
- `add_response_parameter(...)`

`send_success` and `send_error` terminate execution (`never`).

## Response envelope

```json
{
  "message": "...",
  "success": true,
  "parameters": {}
}
```

The `parameters` key is included only when additional response parameters are set.

## CORS behavior

The class uses these constants:

- `CORS_ALLOW_ORIGIN`
- `CORS_ALLOW_HEADERS`

`OPTIONS` requests are handled with HTTP `204` and immediate exit.
