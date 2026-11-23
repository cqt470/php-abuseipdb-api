<?php
    namespace Zexa\Includes;

    use Exception;
    // guard for phpunit
    if(!defined("API_KEY_ABUSEIPDB")){
        include_once __DIR__."/../../env.php";
    }

    class Webhook{
        /**
         * Sends an embed to the configured Discord webhook.
         * Notifications are best-effort: failures are logged and never interrupt the API request.
         *
         * @param array{title?: string|null, description?: string|null, color?: int|string|null, timestamp?: string|null} $embed_data
         * @return bool true if Discord accepted the message
         */
        public static function use_discord(array $embed_data = []): bool{
            if(!\ENABLE_DISCORD_WEBHOOK){
                return false;
            }

            try{
                self::send($embed_data);
                return true;
            }catch(\Throwable $e){
                self::report_failure($e);
                return false;
            }
        }

        private static function report_failure(\Throwable $e): void{
            $message = "Discord webhook failed: ".$e->getMessage();
            error_log($message);

            try{
                (new Logger(\LOGS_DIR))->log("webhook", log_priority::error, $message);
            }catch(\Throwable){
                // logging is best-effort too
            }
        }

        private static function send(array $embed_data): void{
            $payload = [
                "username" => \WEBHOOK_USERNAME,
                "embeds" => [
                    [
                        "title" => $embed_data["title"] ?? "[UNDEFINED]",
                        "description" => $embed_data["description"] ?? "[UNDEFINED]",
                        "color" => self::parse_color($embed_data["color"] ?? null),
                        "timestamp" => $embed_data["timestamp"] ?? date("c"),
                    ]
                ]
            ];

            $options = [
                "http" => [
                    "method" => "POST",
                    "header" => "Content-Type: application/json\r\n",
                    "content" => json_encode($payload, JSON_THROW_ON_ERROR),
                    "timeout" => 5,
                    "ignore_errors" => true
                ]
            ];

            $context = stream_context_create($options);

            if(\URL_DISCORD_WEBHOOK == "YOUR_KEY_HERE"){
                throw new Exception("Set a Discord webhook URL.");
            }

            $response = @file_get_contents(\URL_DISCORD_WEBHOOK, false, $context);

            if($response === false){
                throw new Exception("Didn't get a response from Discord");
            }

            $status = $http_response_header[0] ?? "";
            if (!preg_match("/\\s2\\d{2}\\s/", $status)) {
                throw new Exception("Discord rejected the webhook: ".$response);
            }
        }

        private static function parse_color(int|string|null $color): int{
            if ($color === null) {
                return 0;
            }

            if (is_int($color)) {
                return $color;
            }

            if (!preg_match("/^#?[0-9a-fA-F]{6}$/", $color)) {
                throw new Exception("Discord embed color must be a 24-bit integer or a six-digit hexadecimal color.");
            }

            return hexdec(ltrim($color, "#"));
        }
    }