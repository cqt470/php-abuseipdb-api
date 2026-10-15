<?php
    namespace Zexa\Endpoints;
    
    include_once __DIR__."/../includes/index.php";

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
        private string $ip;
        private string $token;

        public function __construct(){
            parent::__construct("status");

            $this->ip = $this->get_parameter("ip", "string");
            $this->token = $this->get_parameter("token", "string");

            if($this->token != \API_KEY_CUSTOM) $this->send_error("Unauthenticated.", 403);

            $this->register_action("check", fn() => $this->check(), false);

            $this->route_action();
        }

        private function check(){
            if(!Security::validate_ip($this->ip)) $this->send_error("The IP doesn't seem to be valid.");
            
            $info = Security::get_ip_info($this->ip);
            $this->add_response_parameter("info", $info);
            $this->send_success("ok");
        }
    }

    new StatusEndpoint();