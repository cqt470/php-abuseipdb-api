<?php

    include_once __DIR__."/../../env.php";
    include_once __DIR__."/logger.php";

    /**
     * Classe base per la gestione degli endpoint API JSON.
     *
     * La classe non dipende da database, configurazioni globali o file legacy
     * marcati con "-old".
     *
     * @package Zexa\Includes
     * @version 1.0.0
     */
    class Endpoint{
        /**
         * Dati decodificati della richiesta JSON.
         * @var array
         */
        protected array $request_data;
        
        /**
         * Azioni registrate per questo endpoint.
         * @var array
         */
        protected array $actions = [];
        
        /**
         * Parametri aggiuntivi da inserire nella risposta.
         * @var array
         */
        protected array $response_parameters = [];
        
        /**
         * Nome dell'endpoint.
         * @var string
         */
        protected string $endpoint_name;
        
        /**
         * Indica se l'autenticazione è richiesta.
         * @var bool
         */
        protected bool $requires_auth = false;
        
        /**
         * Dati dell'utente autenticato.
         * @var array|null
         */
        protected ?array $user = null;

        protected Logger $logger;

        /**
         * Inizializza l'endpoint e legge il body JSON della richiesta.
         *
         * @param string $endpoint_name Nome dell'endpoint.
         * @param bool $is_post_needed Indica se è richiesto il metodo POST.
         * @param bool $is_body_needed Indica se è richiesto un body non vuoto.
         * @return void
         */
        public function __construct(
            string $endpoint_name = "generic",
            bool $is_post_needed = true,
            bool $is_body_needed = true
        ){
            $this->endpoint_name = $endpoint_name;
            $this->handle_cors();
            $this->logger = new Logger(\LOGS_DIR);

            if ($is_post_needed && ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
                $this->send_error("POST method required.", 405);
            }

            $this->request_data = $this->read_request_body($is_body_needed);
        }

        /**
         * Registra un'azione gestita dall'endpoint.
         *
         * @param string $action_name Nome dell'azione.
         * @param callable $handler Funzione da eseguire.
         * @param bool $requires_csrf Indica se è richiesto il token CSRF.
         * @param bool $requires_auth Indica se è richiesta l'autenticazione.
         * @return void
         */
        protected function register_action(
            string $action_name,
            callable $handler,
            bool $requires_csrf = true,
            bool $requires_auth = false
        ): void{
            $this->actions[$action_name] = [
                "handler" => $handler,
                "requires_csrf" => $requires_csrf,
                "requires_auth" => $requires_auth,
            ];
        }

        /**
         * Retrieves the token, either from the "Authorization" header. Follows the RFC 6750 standard.
         *
         * @return string|null
         */
        protected function get_token(): ?string{
            $bearer_token = getallheaders()["Authorization"] ?? $_SERVER["HTTP_AUTHORIZATION"];
            if(!preg_match('/^Bearer\s+(\S+)\s*$/i', $bearer_token, $matches)){
                return null;
            }

            return $matches[1];
        }

        /**
         * Handles CORS configurations
         *
         * @return void
         */
        protected function handle_cors(): void{
            // CORS configs
            header("Access-Control-Allow-Origin: ".\CORS_ALLOW_ORIGIN);
            header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
            header("Access-Control-Allow-Headers: ".\CORS_ALLOW_HEADERS);
            
            if($_SERVER['REQUEST_METHOD'] === 'OPTIONS'){
                http_response_code(204);
                exit;
            }
        }

        /**
         * Esegue l'azione identificata da un parametro della richiesta.
         *
         * @param string $action_param_name Nome del parametro contenente l'azione.
         * @return void
         */
        protected function route_action(string $action_param_name = "action"): void{
            $action = $this->request_data[$action_param_name] ?? null;

            if (!is_string($action) || $action === "") {
                $this->send_error("Specify a valid action.", 400);
            }

            if (!isset($this->actions[$action])) {
                $this->send_error("Action not permitted.", 400);
            }

            $configuration = $this->actions[$action];

            if ($configuration["requires_auth"]) {
                $this->authenticate_user();
            }

            if ($configuration["requires_csrf"]) {
                $token = $this->request_data["csrf_token"] ?? null;
                if (!is_string($token) || !$this->validate_csrf($token)) {
                    $this->send_error("Invalid CSRF token.", 400);
                }
            }

            ($configuration["handler"])();
        }

        /**
         * Recupera e valida un parametro della richiesta.
         *
         * @param string $name Nome del parametro.
         * @param string $type Tipo atteso: int, bool, float, string o array.
         * @param bool $optional Indica se il parametro è opzionale.
         * @return mixed Valore validato o null se opzionale e assente.
         */
        protected function get_parameter(
            string $name,
            string $type,
            bool $optional = false
        ): mixed{
            $values = $this->get_parameters([
                $name => ["type" => $type, "optional" => $optional],
            ]);

            return $values[$name] ?? null;
        }

        /**
         * Recupera e valida più parametri della richiesta.
         *
         * Ogni configurazione può essere un tipo stringa oppure un array con
         * le chiavi "type" e "optional".
         *
         * @param array $parameters Schema dei parametri da validare.
         * @return array Parametri validati.
         */
        protected function get_parameters(array $parameters): array{
            $result = [];

            foreach ($parameters as $name => $configuration) {
                $type = is_string($configuration)
                    ? $configuration
                    : ($configuration["type"] ?? "string");
                $optional = is_array($configuration)
                    && ($configuration["optional"] ?? false);

                if (!in_array($type, ["int", "bool", "float", "string", "array"], true)) {
                    $this->send_error("Invalid {$type} datatype.", 400);
                }

                if (!array_key_exists($name, $this->request_data)) {
                    if ($optional) {
                        $result[$name] = null;
                        continue;
                    }

                    $this->send_error("The required field \"$name\" is missing.", 400);
                }

                if (!$this->matches_type($this->request_data[$name], $type)) {
                    $actual_type = gettype($this->request_data[$name]);
                    $this->send_error(
                        "\"{$name}\" should be of datatype \"{$type}\", \"{$actual_type}\" received.",
                        400
                    );
                }

                $result[$name] = $this->request_data[$name];
            }

            return $result;
        }

        /**
         * Restituisce il body JSON decodificato.
         *
         * @return array Dati della richiesta.
         */
        protected function get_request_data(): array{
            return $this->request_data;
        }

        /**
         * Hook per implementare l'autenticazione dell'applicazione.
         *
         * @param bool $force_auth Indica se una sessione non autenticata deve
         *                         terminare la richiesta.
         * @return array|null Dati dell'utente o null.
         */
        protected function authenticate_user(bool $force_auth = true): ?array{
            if ($force_auth) {
                $this->send_error("Unauthorized.", 403);
            }

            return null;
        }

        /**
         * Hook per validare il token CSRF dell'applicazione.
         *
         * @param string $csrf_token Token CSRF da validare.
         * @return bool True se il token è valido.
         */
        protected function validate_csrf(string $csrf_token): bool{
            return $csrf_token !== "";
        }

        /**
         * Invia una risposta JSON di successo.
         *
         * @param string|array|null $message Messaggio o payload della risposta.
         * @param int $http_code Codice HTTP della risposta.
         * @return never
         */
        protected function send_success(
            string|array|null $message,
            int $http_code = 200
        ): never{
            $this->send_json($message, $http_code);
        }

        /**
         * Invia una risposta JSON di errore.
         *
         * @param string|array|null $message Messaggio dell'errore.
         * @param int $http_code Codice HTTP della risposta.
         * @return never
         */
        protected function send_error(
            string|array|null $message,
            int $http_code = 400
        ): never{
            $this->send_json($message, $http_code);
        }

        /**
         * Aggiunge un parametro alla risposta JSON.
         *
         * @param string $key Nome del parametro.
         * @param mixed $value Valore del parametro.
         * @return void
         */
        protected function add_response_parameter(string $key, mixed $value): void{
            $this->response_parameters[$key] = $value;
        }

        /**
         * Legge e decodifica il body JSON della richiesta.
         *
         * @param bool $is_body_needed Indica se il body è obbligatorio.
         * @return array Dati decodificati.
         */
        private function read_request_body(bool $is_body_needed): array{
            $raw_body = file_get_contents("php://input");
            $data = $raw_body === false || trim($raw_body) === ""
                ? []
                : json_decode($raw_body, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                if ($is_body_needed) {
                    $this->send_error("The body must be a valid body.", 400);
                }

                return [];
            }

            if ($is_body_needed && $data === []) {
                $this->send_error("A body is needed.", 400);
            }

            return $data;
        }

        /**
         * Verifica che un valore corrisponda al tipo richiesto.
         *
         * @param mixed $value Valore da verificare.
         * @param string $type Tipo atteso.
         * @return bool True se il tipo corrisponde.
         */
        private function matches_type(mixed $value, string $type): bool{
            return match ($type) {
                "int" => is_int($value),
                "bool" => is_bool($value),
                "float" => is_float($value) || is_int($value),
                "string" => is_string($value),
                "array" => is_array($value),
            };
        }

        /**
         * Serializza e invia una risposta JSON, quindi termina l'esecuzione.
         *
         * @param string|array|null $message Messaggio o payload della risposta.
         * @param int $http_code Codice HTTP della risposta.
         * @return never
         */
        private function send_json(string|array|null $message, int $http_code): never{
            $payload = [
                "message" => $message,
                "success" => $http_code >= 200 && $http_code < 300,
            ];
            if ($this->response_parameters !== []) {
                $payload["parameters"] = $this->response_parameters;
            }

            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                http_response_code(500);
                echo '{"message":"internal server error! :(","success":false}';
                exit;
            }

            if (!headers_sent()) {
                header("Content-Type: application/json; charset=utf-8");
            }

            http_response_code($http_code);
            echo $encoded;
            exit;
        }
    }