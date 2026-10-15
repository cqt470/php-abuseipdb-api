<?php
    namespace Zexa\Endpoints;
    
    include_once __DIR__."/../includes/endpoint.php";

    use Endpoint;
    use Zexa\Includes\Security;

    // TODO: add in router later
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    final class StatusEndpoint extends Endpoint{
        public function __construct(){
            parent::__construct("status");

            $this->register_action("check", fn() => $this->send_success("Servizio disponibile"), false);

            $this->route_action();
        }
    }

    new StatusEndpoint();