# Endpoint

Documentazione d'uso di [`src/includes/endpoint.php`](../../src/includes/endpoint.php).

La classe `Endpoint` è autonoma e non richiede database, configurazioni
globali o file `*-old.php`.

## Inclusione

La classe deve essere inclusa esplicitamente dall'endpoint che la utilizza:

```php
require_once __DIR__ . "/../../src/includes/endpoint.php";
```

## Endpoint minimo

Un endpoint concreto estende `Endpoint`, registra una o più azioni e avvia il
routing. Per impostazione predefinita sono richiesti:

- metodo HTTP `POST`;
- corpo JSON non vuoto;
- token CSRF per ogni azione registrata.

### Richiesta

```http
POST /src/api/status.php
Content-Type: application/json
```

```json
{
    "action": "status",
    "csrf_token": "token-dell-applicazione"
}
```

### Implementazione

```php
require_once __DIR__ . "/../../src/includes/endpoint.php";

final class StatusEndpoint extends Endpoint{
    /**
     * Inizializza e avvia l'endpoint.
     * @return void
     */
    public function __construct(){
        parent::__construct("status");

        $this->register_action(
            "status",
            fn() => $this->send_success("Servizio disponibile")
        );

        $this->route_action();
    }
}

new StatusEndpoint();
```

### Risposta

```json
{
    "message": "Servizio disponibile",
    "success": true
}
```

## Metodi della classe

I metodi protetti sono pensati per essere utilizzati dalle classi che
estendono `Endpoint`. I metodi privati sono dettagli interni e non devono
essere richiamati dalle classi figlie.

### `__construct()`

Inizializza il nome dell'endpoint, verifica il metodo HTTP e legge il body
JSON.

```php
public function __construct(
    string $endpoint_name = "generic",
    bool $is_post_needed = true,
    bool $is_body_needed = true
)
```

### `register_action()`

Registra un handler associandolo al nome di un'azione. Consente di specificare
se l'azione richiede CSRF e autenticazione.

```php
protected function register_action(
    string $action_name,
    callable $handler,
    bool $requires_csrf = true,
    bool $requires_auth = false
): void
```

### `route_action()`

Legge il nome dell'azione dal body, verifica che sia registrata, esegue gli
eventuali controlli di autenticazione e CSRF, quindi invoca l'handler.

```php
protected function route_action(string $action_param_name = "action"): void
```

### `get_parameter()`

Recupera e valida un singolo parametro.

```php
protected function get_parameter(
    string $name,
    string $type,
    bool $optional = false
): mixed
```

### `get_parameters()`

Recupera e valida più parametri usando uno schema. Ogni valore può essere un
tipo stringa oppure un array con `type` e `optional`.

```php
protected function get_parameters(array $parameters): array
```

### `get_request_data()`

Restituisce l'intero body JSON già decodificato.

```php
protected function get_request_data(): array
```

### `authenticate_user()`

Hook da sovrascrivere per implementare l'autenticazione applicativa. La
versione predefinita risponde HTTP `403` se l'autenticazione è obbligatoria.

```php
protected function authenticate_user(bool $force_auth = true): ?array
```

### `validate_csrf()`

Hook da sovrascrivere per implementare la validazione del token CSRF.

```php
protected function validate_csrf(string $csrf_token): bool
```

### `send_success()`

Invia una risposta JSON di successo e termina l'esecuzione.

```php
protected function send_success(
    string|array|null $message,
    int $http_code = 200
): never
```

### `send_error()`

Invia una risposta JSON di errore e termina l'esecuzione.

```php
protected function send_error(
    string|array|null $message,
    int $http_code = 400
): never
```

### `add_response_parameter()`

Aggiunge un valore alla chiave `parameters` della risposta JSON.

```php
protected function add_response_parameter(string $key, mixed $value): void
```

### Metodi privati

`read_request_body()` legge il body e lo converte da JSON ad array.
`matches_type()` verifica i tipi supportati.
`send_json()` costruisce e invia la risposta HTTP. Sono metodi interni e non
sono disponibili alle classi figlie.

## Costruttore

```php
parent::__construct(
    string $endpoint_name = "generic",
    bool $is_post_needed = true,
    bool $is_body_needed = true
);
```

- `$endpoint_name` identifica l'endpoint e viene conservato dalla classe.
- `$is_post_needed = false` consente anche richieste `GET`.
- `$is_body_needed = false` consente un corpo vuoto o assente.

Esempio per un endpoint `GET` senza body:

```php
parent::__construct("health", false, false);
```

Il body deve essere un oggetto JSON. JSON non valido, una lista JSON o un body
vuoto quando richiesto producono una risposta HTTP `400`.

## Registrazione e routing delle azioni

```php
$this->register_action(
    string $action_name,
    callable $handler,
    bool $requires_csrf = true,
    bool $requires_auth = false
);
```

Il routing legge il campo `action` dal body:

```php
$this->route_action();
```

È possibile usare un nome diverso:

```php
$this->route_action("operation");
```

In questo caso la richiesta deve contenere `operation` invece di `action`.
Un'azione non registrata o un nome mancante producono HTTP `400`.

### Azioni pubbliche senza CSRF

```php
$this->register_action(
    "health",
    fn() => $this->send_success("OK"),
    false
);
```

## Parametri e validazione

I tipi supportati sono:

- `int`
- `bool`
- `float`
- `string`
- `array`

### Parametro singolo obbligatorio

```php
$ip = $this->get_parameter("ip", "string");
```

### Parametro singolo opzionale

```php
$verbose = $this->get_parameter("verbose", "bool", true);
```

### Più parametri

```php
$values = $this->get_parameters([
    "ip" => "string",
    "verbose" => ["type" => "bool", "optional" => true],
]);

$ip = $values["ip"];
$verbose = $values["verbose"];
```

I parametri opzionali assenti hanno valore `null`. Un campo mancante, un tipo
non supportato o un valore del tipo errato producono HTTP `400`.

La classe non converte automaticamente stringhe numeriche, booleani o altri
valori: il tipo JSON deve essere già corretto.

Per ottenere il body già decodificato:

```php
$body = $this->get_request_data();
```

## Risposte

### Risposta di successo

```php
$this->send_success("IP verificato");
```

È possibile specificare un codice HTTP `2xx`:

```php
$this->send_success(["id" => 42], 201);
```

### Risposta di errore

```php
$this->send_error("Parametro ip non valido", 422);
```

Le risposte hanno sempre questa struttura:

```json
{
    "message": "...",
    "success": true
}
```

### Parametri aggiuntivi

```php
$this->add_response_parameter("cached", true);
$this->send_success("Risultato disponibile");
```

Risultato:

```json
{
    "message": "Risultato disponibile",
    "success": true,
    "parameters": {
        "cached": true
    }
}
```

I metodi `send_success()` e `send_error()` terminano l'esecuzione dopo aver
inviato la risposta e hanno tipo di ritorno `never`.

## CSRF e autenticazione

La classe non conosce il sistema di autenticazione dell'applicazione.
`validate_csrf()` è un hook sostituibile:

```php
protected function validate_csrf(string $token): bool
{
    return hash_equals($_SESSION["csrf_token"] ?? "", $token);
}
```

Anche `authenticate_user()` è un hook. L'implementazione predefinita risponde
HTTP `403` quando `force_auth` è `true`:

```php
protected function authenticate_user(bool $force_auth = true): ?array
{
    $user = $this->findUserFromToken();

    if ($user === null && $force_auth) {
        $this->send_error("Non autorizzato.", 403);
    }

    $this->user = $user;
    return $user;
}
```

Un'azione autenticata si registra così:

```php
$this->register_action(
    "private-status",
    fn() => $this->send_success($this->user),
    true,
    true
);
```

Il routing esegue autenticazione e CSRF prima dell'handler.

## Esempio completo

```php
require_once __DIR__ . "/../../src/includes/endpoint.php";

final class IpCheckEndpoint extends Endpoint{
    /**
     * Inizializza e avvia l'endpoint.
     * @return void
     */
    public function __construct(){
        parent::__construct("ip-check");

        $this->register_action(
            "check",
            fn() => $this->checkIp()
        );

        $this->route_action();
    }

    /**
     * Valida il token CSRF della sessione.
     * @param string $token Token ricevuto.
     * @return bool True se il token è valido.
     */
    protected function validate_csrf(string $token): bool{
        return hash_equals($_SESSION["csrf_token"] ?? "", $token);
    }

    /**
     * Restituisce l'indirizzo IP richiesto.
     * @return never
     */
    private function checkIp(): never{
        $ip = $this->get_parameter("ip", "string");
        $this->send_success(["ip" => $ip]);
    }
}

new IpCheckEndpoint();
```
