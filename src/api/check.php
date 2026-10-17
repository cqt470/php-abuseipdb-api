<?php
    namespace Zexa\Endpoints;
    
    include_once __DIR__."/../includes/index.php";

    use Endpoint;
    use Zexa\Includes\Security;

    final class CheckEndpoint extends Endpoint{
        private string $ip;
        private string $token;

        public function __construct(){
            parent::__construct("status");

            $this->ip = $this->get_parameter("ip", "string");
            $this->token = $this->get_parameter("token", "string");

            if(\ENVIRONMENT != "dev" && !hash_equals(\API_KEY_CUSTOM, $this->token)) $this->send_error("Unauthenticated.", 403);

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

    new CheckEndpoint();