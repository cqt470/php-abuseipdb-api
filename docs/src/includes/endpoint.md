# Endpoint base class

[`src/includes/endpoint.php`](../../../src/includes/endpoint.php) provides the
shared request, routing, validation, CORS, and JSON response behavior.

## Constructor

```php
__construct(
    string $endpoint_name = "generic",
    bool $is_post_needed = true,
    bool $is_body_needed = true
)
```

The constructor configures CORS, creates a `Logger`, enforces `POST` when
requested, and decodes `php://input` as a JSON object.

## Protected request helpers

- `register_action(string $action_name, callable $handler, bool $requires_csrf = true, bool $requires_auth = false): void`
- `route_action(string $action_param_name = "action"): void`
- `get_token(): ?string`
- `get_parameter(string $name, string $type, bool $optional = false): mixed`
- `get_parameters(array $parameters): array`
- `get_request_data(): array`

Supported validation types are `int`, `bool`, `float`, `string`, and `array`.
An optional schema entry has the form `["type" => "string", "optional" => true]`.
Missing required fields and type mismatches end the request with HTTP 400.

`get_token()` accepts only a case-insensitive `Bearer <token>` header. It
checks `getallheaders()` when available and then `HTTP_AUTHORIZATION`.

## Routing hooks

`route_action()` validates the action name, optionally calls
`authenticate_user()`, validates a CSRF token when configured, and invokes the
registered handler. The default CSRF hook accepts every non-empty token. The
default authentication hook returns HTTP 403 when authentication is forced.

## Responses

`send_success()` and `send_error()` call the private JSON serializer and never
return. The envelope is:

```json
{
  "message": "string or null",
  "success": true
}
```

`add_response_parameter()` adds a `parameters` object to that envelope.
`success` is true only for HTTP 2xx responses. The serializer sets
`Content-Type: application/json; charset=utf-8`.
