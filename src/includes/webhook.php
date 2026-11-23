<?php
    namespace Zexa\Includes;

    use Exception;
    // guard for phpunit
    if(!defined("API_KEY_ABUSEIPDB")){
        include_once __DIR__."/../../env.php";
    }

    class Webhook{
        /** Discord limits */
        public const MAX_EMBEDS = 10;
        public const MAX_DESCRIPTION = 4096;
        public const MAX_MESSAGE_CHARS = 6000;

        /**
         * Sends an embed to the configured Discord webhook, or queues it when ENABLE_QUEUE is on
         * (the cron script src/cron/flush.php sends the queue in bulk, grouped by event).
         * Notifications are best-effort: failures are logged and never interrupt the API request.
         *
         * @param array{title?: string|null, description?: string|null, color?: int|string|null, timestamp?: string|null} $embed_data
         * @param discord_event $event the kind of notification, used to group queued notifications
         * @param array $context data used for grouping, e.g. ["ip" => "198.51.100.1"]
         * @return bool true if Discord accepted the message, or if it was queued
         */
        public static function use_discord(array $embed_data = [], discord_event $event = discord_event::generic, array $context = []): bool{
            if(!\ENABLE_DISCORD_WEBHOOK){
                return false;
            }

            try{
                if(Queue::enabled()){
                    return Queue::open(Queue::DISCORD)->push([
                        "event" => $event->value,
                        "context" => $context,
                        "embed" => $embed_data + ["timestamp" => date("c")],
                    ]);
                }

                self::send([$embed_data]);
                return true;
            }catch(\Throwable $e){
                self::report_failure($e);
                return false;
            }
        }

        /**
         * Sends up to 10 embeds in one Discord message, right away (used by the cron flush).
         * Best-effort like use_discord(): failures are logged.
         *
         * @param array $embeds embed data, see use_discord()
         * @return bool true if Discord accepted the message
         */
        public static function send_embeds(array $embeds): bool{
            try{
                self::send($embeds);
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

        private static function send(array $embeds): void{
            if(count($embeds) < 1 || count($embeds) > self::MAX_EMBEDS){
                throw new Exception("A Discord message holds from 1 to ".self::MAX_EMBEDS." embeds.");
            }

            $payload = [
                "username" => \WEBHOOK_USERNAME,
                "embeds" => array_map(fn(array $embed_data) => [
                    "title" => $embed_data["title"] ?? "[UNDEFINED]",
                    "description" => $embed_data["description"] ?? "[UNDEFINED]",
                    "color" => self::parse_color($embed_data["color"] ?? null),
                    "timestamp" => $embed_data["timestamp"] ?? date("c"),
                ], array_values($embeds))
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
