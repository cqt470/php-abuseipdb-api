<?php
    namespace Zexa\Endpoints;
    
    include_once __DIR__."/../includes/index.php";

    use Zexa\Includes\abuse_category;
    use Zexa\Includes\discord_event;
    use Zexa\Includes\Endpoint;
    use Zexa\Includes\Flusher;
    use Zexa\Includes\Queue;
    use Zexa\Includes\log_priority;
    use Zexa\Includes\Security;
    use Zexa\Includes\Webhook;

    final class AbuseIPDBEndpoint extends Endpoint{
        private ?string $token;

        public function __construct(){
            parent::__construct("status");

            $this->token = $this->get_token() ?? $this->get_parameter("token", "string", true) ?? null;
            if(!$this->token) $this->send_error("Unauthenticated.", 401);

            // the example placeholder (or an empty key) must never authenticate anybody
            if(\ENVIRONMENT != "dev" && in_array(trim(\API_KEY_CUSTOM), ["", "YOUR_KEY_HERE"], true)){
                $this->logger->log("auth", log_priority::critical, "API_KEY_CUSTOM is not configured in env.php, all requests are refused.");
                $this->send_error("Server misconfigured.", 500);
            }

            if(\ENVIRONMENT != "dev" && !hash_equals(\API_KEY_CUSTOM, $this->token)){
                $hash = Security::sha256($this->token);
                $this->logger->log("auth", log_priority::warning, "Authentication to /abuseipdb API failed via token. Hash: $hash", true);
                $ip = Security::get_ip();

                Webhook::use_discord([
                    "title" => "Failed authentication",
                    "description" => "IP `$ip` failed to authenticate himself at endpoint /abuseipdb via token, its hash is: `$hash`.",
                    "color" => "#ff0000"
                ], discord_event::failed_auth, ["ip" => $ip]);

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

            if(is_int($info)) $this->send_upstream_error($info);

            $this->add_response_parameter("info", $info);

            $this->logger->log("api", log_priority::info, "IP succesfully checked", true);
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

            // AbuseIPDB category ids: https://www.abuseipdb.com/categories
            if($categories === []) $this->send_error("Specify at least one category.");
            foreach($categories as $category){
                if(abuse_category::from_input($category) === null){
                    $this->send_error("Categories must be integers between 1 and 23.");
                }
            }
            $categories = array_values(array_unique($categories));

            if(!self::is_iso8601($timestamp)) $this->send_error("Invalid timestamp string (must be ISO 8601).");

            if(Queue::enabled()) $this->queue_report(Security::normalize_ip($ip), $categories, $comment, $timestamp);

            ["code" => $http_code, "info" => $info] = Security::report_ip($ip, $categories, $comment, $timestamp);
            if($http_code < 200 || $http_code >= 300){
                $this->logger->log("api", log_priority::warning, "Got a \"$http_code\" HTTP code from the API while reporting.");
                $this->add_response_parameter("info", $info);
                $this->send_upstream_error($http_code);
            }

            $this->add_response_parameter("info", $info);

            $this->logger->log("api", log_priority::info, "IP succesfully reported.", true);
            $this->send_success("ok");
        }

        /**
         * Queues a validated report for the cron flush and answers 202.
         * Reports on an IP that is already queued are merged into it, because
         * AbuseIPDB accepts the same IP only once every 15 minutes.
         *
         * @param int[] $categories
         * @return never
         */
        private function queue_report(string $ip, array $categories, string $comment, string $timestamp): never{
            $item = ["ip" => $ip, "categories" => $categories, "comment" => $comment, "timestamp" => $timestamp];

            try{
                $queued = Queue::open(Queue::REPORTS)->push_or_merge("ip", $item, [Flusher::class, "merge_reports"]);
            }catch(\Throwable $e){
                $this->logger->log("queue", log_priority::critical, "Cannot queue a report: ".$e->getMessage());
                $queued = false;
            }
            if(!$queued) $this->send_error("The report queue is unavailable.", 500);

            $this->logger->log("api", log_priority::info, "Report queued ($ip).", true);
            $this->add_response_parameter("ip", $ip);
            $this->send_success("queued", 202);
        }

        /**
         * Checks for a full ISO 8601 date-time with timezone,
         * e.g. 2026-11-23T08:55:19+01:00, 2026-11-23T07:55:19Z or 2026-11-23T07:55:19.123Z.
         * Free text accepted by DateTime ("tomorrow", "now") is rejected.
         *
         * @param string $timestamp
         * @return bool
         */
        private static function is_iso8601(string $timestamp): bool{
            $pattern = '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(\.\d+)?(Z|[+-](\d{2}):?(\d{2}))$/';
            if(!preg_match($pattern, $timestamp, $m)) return false;

            [, $year, $month, $day, $hour, $minute, $second] = array_map("intval", array_slice($m, 0, 7));
            if(!checkdate($month, $day, $year)) return false;
            if($hour > 23 || $minute > 59 || $second > 59) return false;
            if(isset($m[9]) && ((int)$m[9] > 14 || (int)$m[10] > 59)) return false;

            return true;
        }

        /**
         * Forwards an AbuseIPDB error status to the client with a readable message.
         *
         * @param int $code the HTTP code received from AbuseIPDB
         * @return never
         */
        private function send_upstream_error(int $code): never{
            $msg = match($code){
                0 => "AbuseIPDB could not be reached.",
                401 => "Got a $code HTTP code from the API: check your token.",
                429 => "Got a $code HTTP code from the API: you're sending too many requests.",
                default => "Got a $code HTTP code from the API.",
            };

            // a missing/invalid status means the upstream answer was unusable
            $this->send_error($msg, $code >= 400 && $code <= 599 ? $code : 502);
        }
    }

    new AbuseIPDBEndpoint();