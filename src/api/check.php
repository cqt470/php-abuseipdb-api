<?php
    namespace Zexa\Endpoints;
    
    include_once __DIR__."/../includes/endpoint.php";

    use Endpoint;
    use Zexa\Includes\Security;

    // TODO: add in router later
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");

    if($_SERVER['REQUEST_METHOD'] === 'OPTIONS'){
        http_response_code(204);
        exit;
    }

    final class StatusEndpoint extends Endpoint{
        public function __construct(){
            parent::__construct("status");

            $ip = $this->get_parameter("ip", "string");
            $this->register_action("check", fn() => $this->check(), false);

            $this->route_action();
        }

        private function check(){
            $this->send_success("yoooo");
        }
    }

    new StatusEndpoint();