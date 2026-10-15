<?php
    namespace Zexa\Endpoints;
    
    include_once __DIR__."/../includes/index.php";

use DateTime;
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

    final class ReportEndpoint extends Endpoint{
        private string $ip;
        private array $categories;
        private string $comment;
        private string $timestamp;
        private string $token;

        public function __construct(){
            parent::__construct("status");

            $parameters = $this->get_parameters([
                "ip" => "string",
                "categories" => "array",
                "comment" => "string",
                "timestamp" => ["type" => "string", "optional" => true],
                "token" => "string",
            ]);

            $this->ip = $parameters["ip"];
            $this->categories = $parameters["categories"];
            $this->comment = $parameters["comment"];
            $this->timestamp = $parameters["timestamp"] ?? date("c");
            $this->token = $parameters["token"];

            if($this->token != \API_KEY_CUSTOM) $this->send_error("Unauthenticated.", 403);

            $this->register_action("report", fn() => $this->report(), false);

            $this->route_action();
        }

        private function report(){
            if(!Security::validate_ip($this->ip)) $this->send_error("The IP doesn't seem to be valid.");
            $ip = $this->ip; $categories = implode(",", $this->categories); $comment = $this->comment; $timestamp = $this->timestamp;

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

    new ReportEndpoint();