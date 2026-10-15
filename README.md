# PHP AbuseIPDB API
*A custom AbuseIPDB API used to protect your websites, written in PHP.*

## Environment
The project has been developed in the following environment:
- OS: Debian (WSL)
- PHP Version: 8.4.26

## Endpoint base

[`src/includes/endpoint.php`](src/includes/endpoint.php) contiene la classe
base per gli endpoint JSON. È autonoma e non richiede database, configurazioni
globali o file `*-old.php`.

Un endpoint può estenderla, registrare le proprie azioni e usare
`route_action()` per il routing:

```php
require_once __DIR__ . "/includes/endpoint.php";

final class IpEndpoint extends Endpoint
{
    public function __construct()
    {
        parent::__construct("ip");
        $this->register_action("status", fn() => $this->send_success("OK"));
        $this->route_action();
    }
}
```
