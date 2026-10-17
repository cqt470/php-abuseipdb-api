<?php
    namespace Zexa\Endpoints;
    
    include_once __DIR__."/../includes/index.php";

    use DateTime;
    use Endpoint;
    use Zexa\Includes\log_priority;
    use Zexa\Includes\Security;

    final class AbuseIPDBEndpoint extends Endpoint{
        private ?string $token;

        public function __construct(){
            parent::__construct("status");

            $this->token = $this->get_token() ?? $this->get_parameter("token", "string", true) ?? null;
            if(!$this->token) $this->send_error("Unauthenticated.", 401);

            if(\ENVIRONMENT != "dev" && !hash_equals(\API_KEY_CUSTOM, $this->token)){
                $this->logger->log("auth", log_priority::debug, "Authentication to /abuseipdb API failed via token.", true);
                $this->send_error("Unauthorized.", 403);
            }

            $this->register_action("check", fn() => $this->check(), false);
            $this->register_action("report", fn() => $this->report(), false);

            $this->route_action();
        }

        private function check(){
            $ip = $this->get_parameter("ip", "string");

            if(!Security::validate_ip($ip)) $this->send_error("The IP doesn't seem to be valid.");
            
            $info = Security::get_ip_info($ip);
            $this->add_response_parameter("info", $info);

            $this->send_success("ok");
        }

        private function report(){
            $parameters = $this->get_parameters([
                "ip" => "string",
                "categories" => "array",
                "comment" => "string",
                "timestamp" => ["type" => "string", "optional" => true],
            ]);

            $ip = $parameters["ip"];
            $categories = $parameters["categories"];
            $comment = $parameters["comment"];
            $timestamp = $parameters["timestamp"] ?? date("c");

            if(!Security::validate_ip($ip)) $this->send_error("The IP doesn't seem to be valid.");
            $ip = $ip; $categories = implode(",", $categories); $comment = $comment; $timestamp = $timestamp;

            try{
                $test_timestamp = new DateTime($timestamp);
            }catch(\Throwable){
                $this->send_error("Invalid timestamp string (must be ISO 8601.");
            }
            
            // retrieve data
            $key = \API_KEY_ABUSEIPDB;
            $body = http_build_query([
                "ip"         => $ip,
                "categories" => $categories,
                "comment"    => $comment,
                "timestamp"  => $timestamp,
            ]);
            $context = stream_context_create([
                "http" => [
                    "method" => "POST",
                    "header" => "Key: $key\r\nAccept: application/json\r\n",
                    "content" => $body,
                    "ignore_errors" => true, // reads errors on 4xx and 5xx
                    "timeout" => 16,
                ]
            ]);

            $info = json_decode(file_get_contents("https://api.abuseipdb.com/api/v2/report", false, $context), true);

            $this->add_response_parameter("info", $info);

            $this->send_success("ok");
        }
    }

    new AbuseIPDBEndpoint();